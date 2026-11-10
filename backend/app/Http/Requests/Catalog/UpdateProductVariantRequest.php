<?php

namespace App\Http\Requests\Catalog;

use App\Models\Organization;
use App\Models\ProductVariant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates edits to a variant. Size and color are fixed once a variant exists, so they are not accepted.
 */
class UpdateProductVariantRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Organization $organization */
        $organization = $this->attributes->get('organization');

        /** @var ProductVariant $variant */
        $variant = $this->route('variant');

        return [
            'sku' => [
                'required',
                'string',
                'max:100',
                Rule::unique(ProductVariant::class, 'sku')
                    ->where('organization_id', $organization->id)
                    ->ignore($variant->id),
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique(ProductVariant::class, 'barcode')
                    ->where('organization_id', $organization->id)
                    ->ignore($variant->id),
            ],
            'price' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999999.99'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Normalize the SKU to upper case before validation.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('sku'))) {
            $this->merge(['sku' => strtoupper($this->input('sku'))]);
        }
    }
}
