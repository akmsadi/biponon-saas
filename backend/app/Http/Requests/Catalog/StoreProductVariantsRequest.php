<?php

namespace App\Http\Requests\Catalog;

use App\Models\Color;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates a batch of new variants for one product. Every variant shares the same optional price.
 */
class StoreProductVariantsRequest extends FormRequest
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

        return [
            'variants' => ['required', 'array', 'min:1', 'max:500'],
            'variants.*.size_id' => [
                'required',
                'integer',
                Rule::exists(Size::class, 'id')
                    ->where('organization_id', $organization->id)
                    ->where('is_active', true),
            ],
            'variants.*.color_id' => [
                'required',
                'integer',
                Rule::exists(Color::class, 'id')
                    ->where('organization_id', $organization->id)
                    ->where('is_active', true),
            ],
            'variants.*.sku' => [
                'required',
                'string',
                'max:100',
                'distinct',
                Rule::unique(ProductVariant::class, 'sku')->where('organization_id', $organization->id),
            ],
            'variants.*.barcode' => [
                'nullable',
                'string',
                'max:100',
                'distinct',
                Rule::unique(ProductVariant::class, 'barcode')->where('organization_id', $organization->id),
            ],
            'price' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999999.99'],
        ];
    }

    /**
     * Reject size and color combinations the product already has or that repeat within the batch.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Product $product */
                $product = $this->route('product');

                $takenCombinations = $product->productVariants()
                    ->get(['size_id', 'color_id'])
                    ->map(fn (ProductVariant $variant): string => $variant->size_id.'-'.$variant->color_id)
                    ->all();

                foreach ((array) $this->input('variants', []) as $index => $variant) {
                    $combination = ($variant['size_id'] ?? '').'-'.($variant['color_id'] ?? '');

                    if (in_array($combination, $takenCombinations, true)) {
                        $validator->errors()->add("variants.{$index}.size_id", __('This size and color combination already exists.'));
                    }

                    $takenCombinations[] = $combination;
                }
            },
        ];
    }

    /**
     * Normalize SKUs to trimmed upper case before validation.
     */
    protected function prepareForValidation(): void
    {
        $variants = $this->input('variants');

        if (! is_array($variants)) {
            return;
        }

        $this->merge([
            'variants' => array_map(function (mixed $variant): mixed {
                if (is_array($variant) && is_string($variant['sku'] ?? null)) {
                    $variant['sku'] = strtoupper(trim($variant['sku']));
                }

                return $variant;
            }, $variants),
        ]);
    }
}
