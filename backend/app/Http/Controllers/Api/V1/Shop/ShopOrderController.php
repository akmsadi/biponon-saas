<?php

namespace App\Http\Controllers\Api\V1\Shop;

use App\Actions\Shop\PlaceShopOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\PlaceShopOrderRequest;
use App\Http\Resources\Shop\ShopOrderResource;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;

class ShopOrderController extends Controller
{
    /**
     * Place a cash-on-delivery order. It lands in Biponon as a draft sales order for a manager to confirm.
     */
    public function store(PlaceShopOrderRequest $request, Organization $shop, PlaceShopOrder $placeShopOrder): JsonResponse
    {
        $order = $placeShopOrder->handle($shop, $request->validated());

        return (new ShopOrderResource($order->load(['lines', 'organization'])))
            ->response()
            ->setStatusCode(201);
    }
}
