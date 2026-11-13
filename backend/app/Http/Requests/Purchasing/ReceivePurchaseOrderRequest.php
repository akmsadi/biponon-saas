<?php

namespace App\Http\Requests\Purchasing;

use App\Concerns\ValidatesNewSerialNumbers;
use App\Models\Organization;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Warehouse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates a receipt against a purchase order. Each order line is split across one or more active
 * warehouses; a quantity of 0 skips that split, the total per line may not exceed what is still expected,
 * and each split may carry exactly one new serial number per unit.
 */
class ReceivePurchaseOrderRequest extends FormRequest
{
    use ValidatesNewSerialNumbers;

    public const MAX_SPLITS_PER_LINE = 10;

    /**
     * Orders of another organization are not found.
     */
    public function authorize(): bool
    {
        abort_unless($this->purchaseOrder()->organization_id === $this->currentOrganization()->id, 404);

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'received_at' => ['required', 'date', 'before_or_equal:+5 minutes'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.purchase_order_line_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists(PurchaseOrderLine::class, 'id')->where('purchase_order_id', $this->purchaseOrder()->id),
            ],
            'lines.*.allocations' => ['required', 'array', 'min:1', 'max:'.self::MAX_SPLITS_PER_LINE],
            'lines.*.allocations.*.warehouse_id' => [
                'nullable',
                'integer',
                Rule::exists(Warehouse::class, 'id')
                    ->where('organization_id', $this->currentOrganization()->id)
                    ->where('is_active', true),
            ],
            'lines.*.allocations.*.quantity' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'lines.*.allocations.*.serials' => ['array'],
            'lines.*.allocations.*.serials.*' => ['string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'received_at.before_or_equal' => __('The received date cannot be in the future.'),
            'lines.*.purchase_order_line_id.exists' => __('This line is not on the order.'),
            'lines.*.allocations.*.warehouse_id.exists' => __('Choose an active warehouse.'),
        ];
    }

    /**
     * Check every split has a warehouse when it has a quantity, a line goes to each warehouse once, no line
     * is over-received, something is received at all, and the serials are new.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $orderLines = $this->purchaseOrder()->lines()->get()->keyBy('id');
                $totalUnits = 0;
                $serialGroups = [];

                foreach ($this->input('lines') as $index => $line) {
                    $orderLine = $orderLines[(int) $line['purchase_order_line_id']];
                    $lineUnits = 0;
                    $warehouses = [];

                    foreach ($line['allocations'] as $split => $allocation) {
                        $quantity = (int) ($allocation['quantity'] ?? 0);
                        $warehouseId = $allocation['warehouse_id'] ?? null;
                        $key = "lines.{$index}.allocations.{$split}";

                        if ($quantity > 0 && $warehouseId === null) {
                            $validator->errors()->add("{$key}.warehouse_id", __('Choose a warehouse.'));
                        }

                        if ($quantity > 0 && $warehouseId !== null && isset($warehouses[(int) $warehouseId])) {
                            $validator->errors()->add("{$key}.warehouse_id", __('This warehouse is already used for this line. Combine the quantities instead.'));
                        }

                        if ($quantity > 0 && $warehouseId !== null) {
                            $warehouses[(int) $warehouseId] = true;
                        }

                        $lineUnits += $quantity;
                        $serialGroups["{$key}.serials"] = ['quantity' => $quantity, 'serials' => $allocation['serials'] ?? []];
                    }

                    if ($lineUnits > $orderLine->quantityRemaining()) {
                        $validator->errors()->add("lines.{$index}.allocations", trans_choice(
                            'Only :count unit is still expected on this line.|Only :count units are still expected on this line.',
                            $orderLine->quantityRemaining(),
                        ));
                    }

                    $totalUnits += $lineUnits;
                }

                if ($totalUnits === 0) {
                    $validator->errors()->add('lines', __('Enter a quantity for at least one line.'));
                }

                $this->validateNewSerials($validator, $this->currentOrganization(), $serialGroups);
            },
        ];
    }

    /**
     * Treat a blank quantity as 0 and split each split's serials text into a list (blank means none).
     */
    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines');

        if (! is_array($lines)) {
            return;
        }

        $this->merge([
            'lines' => array_map(function (mixed $line): mixed {
                if (! is_array($line) || ! is_array($line['allocations'] ?? null)) {
                    return $line;
                }

                $line['allocations'] = array_map(function (mixed $allocation): mixed {
                    if (! is_array($allocation)) {
                        return $allocation;
                    }

                    if (in_array($allocation['quantity'] ?? null, [null, ''], true)) {
                        $allocation['quantity'] = 0;
                    }

                    if (in_array($allocation['warehouse_id'] ?? null, [null, ''], true)) {
                        $allocation['warehouse_id'] = null;
                    }

                    $allocation['serials'] = is_string($allocation['serials'] ?? null)
                        ? self::splitSerials($allocation['serials'])
                        : ($allocation['serials'] ?? []);

                    return $allocation;
                }, $line['allocations']);

                return $line;
            }, $lines),
        ]);
    }

    public function purchaseOrder(): PurchaseOrder
    {
        return $this->route('purchaseOrder');
    }

    private function currentOrganization(): Organization
    {
        return $this->attributes->get('organization');
    }
}
