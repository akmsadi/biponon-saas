<?php

namespace App\Http\Requests\Sales;

use App\Models\Customer;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a draft sales order: an active customer and warehouse, and up to 50 lines of active variants,
 * each with a unit price.
 */
class SalesOrderRequest extends FormRequest
{
    /**
     * Highest unit price accepted, so line totals stay exact in whole cents.
     */
    public const MAX_UNIT_PRICE = '99999999.99';

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organization = $this->currentOrganization();

        return [
            'customer_id' => [
                'required',
                'integer',
                Rule::exists(Customer::class, 'id')
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
            'deliver_by' => ['nullable', 'date_format:Y-m-d'],
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
            'lines.*.unit_price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:'.self::MAX_UNIT_PRICE],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_id.exists' => __('Choose an active customer.'),
            'warehouse_id.exists' => __('Choose an active warehouse.'),
            'lines.*.product_variant_id.distinct' => __('This variant is already on another line. Combine the quantities instead.'),
            'lines.*.product_variant_id.exists' => __('Choose an active variant of an active product.'),
            'lines.*.unit_price.required' => __('Enter a unit price.'),
            'lines.*.unit_price.decimal' => __('Use at most two decimal places.'),
        ];
    }

    private function currentOrganization(): Organization
    {
        return $this->attributes->get('organization');
    }
}
