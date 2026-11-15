<?php

namespace App\Http\Requests\Operations;

use App\Concerns\ValidatesOutgoingSerialNumbers;
use App\Models\Organization;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates a dispatch: a source warehouse (active or not) and up to 50 lines of variants with quantities no
 * larger than the source holds, each optionally naming the serial numbers of the units that leave.
 */
class DispatchStockRequest extends FormRequest
{
    use ValidatesOutgoingSerialNumbers;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organization = $this->currentOrganization();

        return [
            'warehouse_id' => [
                'required',
                'integer',
                Rule::exists(Warehouse::class, 'id')->where('organization_id', $organization->id),
            ],
            'dispatched_at' => ['required', 'date', 'before_or_equal:+5 minutes'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.product_variant_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists(ProductVariant::class, 'id')->where('organization_id', $organization->id),
            ],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'lines.*.serials' => ['array'],
            'lines.*.serials.*' => ['string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dispatched_at.before_or_equal' => __('The dispatch date cannot be in the future.'),
            'lines.*.product_variant_id.distinct' => __('This variant is already on another line. Combine the quantities instead.'),
        ];
    }

    /**
     * Check that the warehouse holds enough of every variant, and that any serials given are one per unit,
     * not repeated, and are units of that variant in stock there.
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

                $warehouse = $this->currentOrganization()->warehouses()->findOrFail($this->input('warehouse_id'));
                $lines = $this->input('lines');

                $this->rejectQuantitiesAboveAvailable($validator, $warehouse, $lines);
                $this->rejectInvalidSerials($validator, $warehouse, $lines);
            },
        ];
    }

    /**
     * Split each line's serials text into a trimmed list without blank lines.
     */
    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines');

        if (! is_array($lines)) {
            return;
        }

        $this->merge([
            'lines' => array_map(function (mixed $line): mixed {
                if (is_array($line) && is_string($line['serials'] ?? null)) {
                    $line['serials'] = array_values(array_filter(
                        array_map('trim', preg_split('/\r\n|\r|\n/', $line['serials'])),
                        fn (string $serial): bool => $serial !== '',
                    ));
                }

                return $line;
            }, $lines),
        ]);
    }

    /**
     * @param  array<int, array{product_variant_id: int|string, quantity: int|string}>  $lines
     */
    private function rejectQuantitiesAboveAvailable(Validator $validator, Warehouse $warehouse, array $lines): void
    {
        $available = $this->currentOrganization()->stockBalances()
            ->where('warehouse_id', $warehouse->id)
            ->whereIn('product_variant_id', array_column($lines, 'product_variant_id'))
            ->pluck('quantity', 'product_variant_id');

        foreach ($lines as $index => $line) {
            $onHand = (int) ($available[$line['product_variant_id']] ?? 0);

            if ((int) $line['quantity'] > $onHand) {
                $validator->errors()->add("lines.{$index}.quantity", __('Only :available available in :warehouse.', [
                    'available' => $onHand,
                    'warehouse' => $warehouse->code,
                ]));
            }
        }
    }

    /**
     * @param  array<int, array{product_variant_id: int|string, quantity: int|string, serials?: list<string>}>  $lines
     */
    private function rejectInvalidSerials(Validator $validator, Warehouse $warehouse, array $lines): void
    {
        $groups = [];

        foreach ($lines as $index => $line) {
            $groups["lines.{$index}.serials"] = [
                'quantity' => (int) $line['quantity'],
                'serials' => $line['serials'] ?? [],
                'product_variant_id' => (int) $line['product_variant_id'],
                'warehouse_id' => $warehouse->id,
                'warehouse_code' => $warehouse->code,
            ];
        }

        $this->validateOutgoingSerials($validator, $this->currentOrganization(), $groups);
    }

    private function currentOrganization(): Organization
    {
        return $this->attributes->get('organization');
    }
}
