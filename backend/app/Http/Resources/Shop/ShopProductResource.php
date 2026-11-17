<?php

namespace App\Http\Resources\Shop;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A product page: description, every photo, the sizes and colors on offer and each orderable variant.
 * Expects `images` and the orderable `productVariants` (with `size`, `color` and `shop_stock`) to be loaded.
 *
 * @mixin Product
 */
class ShopProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $variants = $this->productVariants;

        return [
            ...(new ShopProductCardResource($this->resource))->toArray($request),
            'description' => $this->description,
            'images' => $this->images->map(fn (ProductImage $image) => $image->url())->values(),
            'sizes' => $variants->pluck('size')->unique('id')
                ->sortBy([['sort_order', 'asc'], ['name', 'asc']])
                ->map(fn ($size) => ['id' => $size->id, 'name' => $size->name])
                ->values(),
            'colors' => $variants->pluck('color')->unique('id')
                ->sortBy([['sort_order', 'asc'], ['name', 'asc']])
                ->map(fn ($color) => ['id' => $color->id, 'name' => $color->name, 'hex' => $color->hex_code])
                ->values(),
            'variants' => ShopVariantResource::collection($variants->values()),
        ];
    }
}
