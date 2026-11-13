<?php

namespace App\Http\Requests\Sales;

use App\Concerns\ValidatesOutgoingSerialNumbers;
use App\Models\Organization;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\Warehouse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates a dispatch against a sales order. Each order line is split across one or more warehouses of the
 * organization (active or not, so stock can still leave an inactive one); a quantity of 0 skips that split.
 * The total per line may not exceed what is still to send, each split may not exceed what its warehouse
 * holds, and each split may name exactly one in-stock serial number per unit.
 */
class DispatchSalesOrderRequest extends FormRequest
{
    use ValidatesOutgoingSerialNumbers;

    public const MAX_SPLITS_PER_LINE = 10;

    /**
     * Orders of another organization are not found.
     */
    public function authorize(): bool
    {
        abort_unless($this->salesOrder()->organization_id === $this->currentOrganization()->id, 404);

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
            'dispatched_at' => ['required', 'date', 'before_or_equal:+5 minutes'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.sales_order_line_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists(SalesOrderLine::class, 'id')->where('sales_order_id', $this->salesOrder()->id),
            ],
            'lines.*.allocations' => ['required', 'array', 'min:1', 'max:'.self::MAX_SPLITS_PER_LINE],
            'lines.*.allocations.*.warehouse_id' => [
                'nullable',
                'integer',
                Rule::exists(Warehouse::class, 'id')->where('organization_id', $this->currentOrganization()->id),
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
            'dispatched_at.before_or_equal' => __('The dispatch date cannot be in the future.'),
            'lines.*.sales_order_line_id.exists' => __('This line is not on the order.'),
        ];
    }

    /**
     * Check every split has a warehouse when it has a quantity, a line leaves each warehouse once, no split
     * takes more than its warehouse holds, no line is over-sent, something is sent at all, and the serials
     * are in stock where they leave from.
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

                $organization = $this->currentOrganization();
                $orderLines = $this->salesOrder()->lines()->get()->keyBy('id');
                $warehouses = $organization->warehouses()
                    ->whereIn('id', collect($this->input('lines'))->pluck('allocations')->flatten(1)->pluck('warehouse_id')->filter()->unique())
                    ->get()
                    ->keyBy('id');
                $balances = $organization->stockBalances()
                    ->whereIn('product_variant_id', $orderLines->pluck('product_variant_id'))
                    ->get()
                    ->mapWithKeys(fn ($balance): array => ["{$balance->product_variant_id}:{$balance->warehouse_id}" => $balance->quantity]);
                $totalUnits = 0;
                $serialGroups = [];

                foreach ($this->input('lines') as $index => $line) {
                    $orderLine = $orderLines[(int) $line['sales_order_line_id']];
                    $lineUnits = 0;
                    $used = [];

                    foreach ($line['allocations'] as $split => $allocation) {
                        $quantity = (int) ($allocation['quantity'] ?? 0);
                        $warehouseId = $allocation['warehouse_id'] === null ? null : (int) $allocation['warehouse_id'];
                        $key = "lines.{$index}.allocations.{$split}";
                        $lineUnits += $quantity;

                        if ($quantity === 0) {
                            continue;
                        }

                        if ($warehouseId === null) {
                            $validator->errors()->add("{$key}.warehouse_id", __('Choose a warehouse.'));

                            continue;
                        }

                        if (isset($used[$warehouseId])) {
                            $validator->errors()->add("{$key}.warehouse_id", __('This warehouse is already used for this line. Combine the quantities instead.'));

                            continue;
                        }

                        $used[$warehouseId] = true;
                        $warehouse = $warehouses[$warehouseId];
                        $onHand = (int) ($balances["{$orderLine->product_variant_id}:{$warehouseId}"] ?? 0);

                        if ($quantity > $onHand) {
                            $validator->errors()->add("{$key}.quantity", __('Only :available available in :warehouse.', [
                                'available' => $onHand,
                                'warehouse' => $warehouse->code,
                            ]));
                        }

                        $serialGroups["{$key}.serials"] = [
                            'quantity' => $quantity,
                            'serials' => $allocation['serials'] ?? [],
                            'product_variant_id' => $orderLine->product_variant_id,
                            'warehouse_id' => $warehouseId,
                            'warehouse_code' => $warehouse->code,
                        ];
                    }

                    if ($lineUnits > $orderLine->quantityRemaining()) {
                        $validator->errors()->add("lines.{$index}.allocations", trans_choice(
                            'Only :count unit is still to send on this line.|Only :count units are still to send on this line.',
                            $orderLine->quantityRemaining(),
                        ));
                    }

                    $totalUnits += $lineUnits;
                }

                if ($totalUnits === 0) {
                    $validator->errors()->add('lines', __('Enter a quantity for at least one line.'));
                }

                $this->validateOutgoingSerials($validator, $organization, $serialGroups);
            },
        ];
    }

    /**
     * Treat a blank quantity as 0, a blank warehouse as none, and split each split's serials text into a list.
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
                        ? array_values(array_filter(
                            array_map('trim', preg_split('/\r\n|\r|\n/', $allocation['serials'])),
                            fn (string $serial): bool => $serial !== '',
                        ))
                        : ($allocation['serials'] ?? []);

                    return $allocation;
                }, $line['allocations']);

                return $line;
            }, $lines),
        ]);
    }

    public function salesOrder(): SalesOrder
    {
        return $this->route('salesOrder');
    }

    private function currentOrganization(): Organization
    {
        return $this->attributes->get('organization');
    }
}
