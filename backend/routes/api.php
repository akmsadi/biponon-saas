<?php

use App\Http\Controllers\Api\V1\Shop\ShopController;
use App\Http\Controllers\Api\V1\Shop\ShopOrderController;
use App\Http\Controllers\Api\V1\Shop\ShopProductController;
use Illuminate\Support\Facades\Route;

/*
| Public online shop API, called by the Next.js shop's server (frontend/). No login: every route is scoped to
| one organization's shop by its slug, and only active organizations have a shop ({shop} is bound in
| AppServiceProvider, because this file is not loaded once routes are cached).
*/

Route::prefix('v1/shops/{shop}')->name('api.v1.shops.')->group(function () {
    Route::get('/', [ShopController::class, 'show'])->name('show');
    Route::get('products', [ShopProductController::class, 'index'])->name('products.index');
    Route::get('products/{productSlug}', [ShopProductController::class, 'show'])->name('products.show');
    Route::post('orders', [ShopOrderController::class, 'store'])->middleware('throttle:shop-orders')->name('orders.store');
});
