<?php

namespace App\Http\Resources\Shop;

use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One orderable size × color of a product. Stock is a yes/no; exact counts stay private.
 * Expects `size`, `color` and `shop_stock` to be loaded.
 *
 * @mixin ProductVariant
 */
class ShopVariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'size_id' => $this->size_id,
            'color_id' => $this->color_id,
            'price' => $this->price,
            'in_stock' => (int) $this->shop_stock > 0,
        ];
    }
}
