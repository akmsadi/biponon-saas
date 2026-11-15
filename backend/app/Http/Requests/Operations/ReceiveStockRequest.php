<?php

namespace App\Http\Requests\Operations;

use App\Concerns\ValidatesNewSerialNumbers;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates a stock receipt: one active warehouse and up to 50 lines of active variants with quantities,
 * each optionally carrying exactly one serial number per unit.
 */
class ReceiveStockRequest extends FormRequest
{
    use ValidatesNewSerialNumbers;

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
                Rule::exists(Warehouse::class, 'id')
                    ->where('organization_id', $organization->id)
                    ->where('is_active', true),
            ],
            'received_at' => ['required', 'date', 'before_or_equal:+5 minutes'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.product_variant_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists(ProductVariant::class, 'id')
                    ->where('organization_id', $organization->id)
                    ->where('is_active', true)
                    ->where(fn (Builder $query) => $query->whereIn(
                        'product_id',
                        Product::select('id')->where('organization_id', $organization->id)->where('is_active', true),
                    )),
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
            'received_at.before_or_equal' => __('The received date cannot be in the future.'),
            'lines.*.product_variant_id.distinct' => __('This variant is already on another line. Combine the quantities instead.'),
            'lines.*.product_variant_id.exists' => __('Choose an active variant of an active product.'),
        ];
    }

    /**
     * Check serials: one per unit when given, unique within the receipt and not already known to the organization.
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

                $groups = [];

                foreach ($this->input('lines', []) as $index => $line) {
                    $groups["lines.{$index}.serials"] = ['quantity' => (int) $line['quantity'], 'serials' => $line['serials'] ?? []];
                }

                $this->validateNewSerials($validator, $this->currentOrganization(), $groups);
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
                    $line['serials'] = self::splitSerials($line['serials']);
                }

                return $line;
            }, $lines),
        ]);
    }

    private function currentOrganization(): Organization
    {
        return $this->attributes->get('organization');
    }
}
