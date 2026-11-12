<?php

use App\Http\Controllers\Catalog\ColorController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Catalog\ProductVariantController;
use App\Http\Controllers\Catalog\SizeController;
use App\Http\Controllers\CurrentOrganizationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Inventory\SerialNumberController;
use App\Http\Controllers\Inventory\StockMovementController;
use App\Http\Controllers\Inventory\StockOverviewController;
use App\Http\Controllers\Operations\DispatchStockController;
use App\Http\Controllers\Operations\ReceiveStockController;
use App\Http\Controllers\Operations\ReturnStockController;
use App\Http\Controllers\Operations\TransferStockController;
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
            Route::get('transfer', [TransferStockController::class, 'create'])->name('transfer.create');
            Route::post('transfer', [TransferStockController::class, 'store'])->name('transfer.store');
            Route::get('dispatch', [DispatchStockController::class, 'create'])->name('dispatch.create');
            Route::post('dispatch', [DispatchStockController::class, 'store'])->name('dispatch.store');
            Route::get('return', [ReturnStockController::class, 'create'])->name('return.create');
            Route::post('return', [ReturnStockController::class, 'store'])->name('return.store');
        });

        Route::prefix('inventory')->name('inventory.')->group(function () {
            Route::get('stock', [StockOverviewController::class, 'index'])->name('stock.index');
            Route::get('serials', [SerialNumberController::class, 'index'])->name('serials.index');
            Route::get('serials/{serialNumber}/history', [SerialNumberController::class, 'history'])->name('serials.history');
            Route::get('movements', [StockMovementController::class, 'index'])->name('movements.index');
            Route::get('movements/export', [StockMovementController::class, 'export'])->name('movements.export');
            Route::get('movements/{stockMovement}/serials', [StockMovementController::class, 'serials'])->name('movements.serials');
        });
    });
});

require __DIR__.'/settings.php';
