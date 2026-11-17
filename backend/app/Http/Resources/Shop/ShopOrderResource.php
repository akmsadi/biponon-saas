<?php

namespace App\Http\Resources\Shop;

use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The confirmation a shopper sees after ordering. Expects `lines` and `organization` to be loaded.
 *
 * @mixin SalesOrder
 */
class ShopOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $totalCents = $this->lines->sum(fn (SalesOrderLine $line) => $line->totalCents());

        return [
            'number' => $this->number,
            'total' => number_format($totalCents / 100, 2, '.', ''),
            'currency' => $this->organization->setting('currency'),
            'payment' => 'cash_on_delivery',
        ];
    }
}
