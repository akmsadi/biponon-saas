<?php

use App\Http\Controllers\Catalog\ColorController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Catalog\ProductVariantController;
use App\Http\Controllers\Catalog\SizeController;
use App\Http\Controllers\CurrentOrganizationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Inventory\StockOverviewController;
use App\Http\Controllers\Operations\ReceiveStockController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::put('current-organization', [CurrentOrganizationController::class, 'update'])->name('current-organization.update');

    // Everything that shows organization data goes in here
    Route::middleware('organization')->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::prefix('catalog')->name('catalog.')->group(function () {
            Route::resource('sizes', SizeController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::resource('colors', ColorController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::resource('products', ProductController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
            Route::resource('products.variants', ProductVariantController::class)->only(['store', 'update', 'destroy'])->shallow();
        });

        Route::resource('warehouses', WarehouseController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::prefix('operations')->name('operations.')->group(function () {
            Route::get('receive', [ReceiveStockController::class, 'create'])->name('receive.create');
            Route::post('receive', [ReceiveStockController::class, 'store'])->name('receive.store');
        });

        Route::prefix('inventory')->name('inventory.')->group(function () {
            Route::get('stock', [StockOverviewController::class, 'index'])->name('stock.index');
            // Route::get('serials', ...)->name('serials');
            // Route::get('movements', ...)->name('movements');
        });
    });
});

require __DIR__.'/settings.php';
