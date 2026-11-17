<?php

namespace App\Http\Resources\Shop;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A product in the shop's product grid: main photo, price range and whether any variant is in stock.
 * Expects `images` and the orderable `productVariants` (with `shop_stock`) to be loaded.
 *
 * @mixin Product
 */
class ShopProductCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $prices = $this->productVariants->map(fn (ProductVariant $variant) => (float) $variant->price);
        $mainImage = $this->images->first();

        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'image' => $mainImage?->url(),
            'price' => [
                'min' => number_format($prices->min(), 2, '.', ''),
                'max' => number_format($prices->max(), 2, '.', ''),
            ],
            'in_stock' => $this->productVariants->contains(fn (ProductVariant $variant) => (int) $variant->shop_stock > 0),
        ];
    }
}
