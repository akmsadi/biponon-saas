<?php

namespace App\Http\Requests\Purchasing;

use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a draft purchase order: an active supplier and warehouse, and up to 50 lines of active variants.
 * Unit costs are optional and accepted only from managers and admins; members' costs are dropped.
 */
class PurchaseOrderRequest extends FormRequest
{
    /**
     * Highest unit cost accepted, so line totals stay exact in whole cents.
     */
    public const MAX_UNIT_COST = '99999999.99';

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organization = $this->currentOrganization();

        return [
            'supplier_id' => [
                'required',
                'integer',
                Rule::exists(Supplier::class, 'id')
                    ->where('organization_id', $organization->id)
                    ->where('is_active', true),
            ],
            'warehouse_id' => [
                'required',
                'integer',
                Rule::exists(Warehouse::class, 'id')
                    ->where('organization_id', $organization->id)
                    ->where('is_active', true),
            ],
            'expected_at' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'],
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
            'lines.*.unit_cost' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:'.self::MAX_UNIT_COST],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'supplier_id.exists' => __('Choose an active supplier.'),
            'warehouse_id.exists' => __('Choose an active warehouse.'),
            'lines.*.product_variant_id.distinct' => __('This variant is already on another line. Combine the quantities instead.'),
            'lines.*.product_variant_id.exists' => __('Choose an active variant of an active product.'),
            'lines.*.unit_cost.decimal' => __('Use at most two decimal places.'),
        ];
    }

    /**
     * Whether the user may see and set unit costs (managers and admins).
     */
    public function canSetCosts(): bool
    {
        return Organization::roleIncludes($this->currentOrganization()->pivot?->role, 'manager');
    }

    /**
     * Drop costs sent by members, and treat a blank cost as unknown.
     */
    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines');

        if (! is_array($lines)) {
            return;
        }

        $canSetCosts = $this->canSetCosts();

        $this->merge([
            'lines' => array_map(function (mixed $line) use ($canSetCosts): mixed {
                if (! is_array($line)) {
                    return $line;
                }

                if (! $canSetCosts) {
                    unset($line['unit_cost']);
                } elseif (is_string($line['unit_cost'] ?? null) && trim($line['unit_cost']) === '') {
                    $line['unit_cost'] = null;
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
