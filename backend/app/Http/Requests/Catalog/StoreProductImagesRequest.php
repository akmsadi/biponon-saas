<?php

namespace App\Http\Requests\Catalog;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates photos added to a product: JPG, PNG or WebP up to 4 MB each, at most 8 photos per product in total.
 */
class StoreProductImagesRequest extends FormRequest
{
    /** Largest accepted file, in kilobytes. */
    public const MAX_KILOBYTES = 4096;

    /**
     * Another organization's product is "not found", before any validation runs.
     */
    public function authorize(): bool
    {
        /** @var Product $product */
        $product = $this->route('product');

        abort_unless($product->organization_id === $this->attributes->get('organization')?->id, 404);

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
            'images' => ['required', 'array', 'min:1', 'max:'.ProductImage::MAX_PER_PRODUCT],
            'images.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_KILOBYTES],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Product $product */
                $product = $this->route('product');
                $room = ProductImage::MAX_PER_PRODUCT - $product->images()->count();

                if (count((array) $this->file('images', [])) > $room) {
                    $validator->errors()->add('images', $room > 0
                        ? __('A product can have at most :max photos. You can add :room more.', ['max' => ProductImage::MAX_PER_PRODUCT, 'room' => $room])
                        : __('A product can have at most :max photos. Delete one first.', ['max' => ProductImage::MAX_PER_PRODUCT]));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'images.*.mimes' => __('Photos must be JPG, PNG or WebP files.'),
            'images.*.max' => __('Each photo can be at most 4 MB.'),
        ];
    }
}
