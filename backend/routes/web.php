<?php

use App\Http\Controllers\Catalog\ColorController;
use App\Http\Controllers\Catalog\SizeController;
use App\Http\Controllers\CurrentOrganizationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::put('current-organization', [CurrentOrganizationController::class, 'update'])
        ->name('current-organization.update');

    // Everything that shows organization data goes in here
    Route::middleware('organization')->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::prefix('catalog')->name('catalog.')->group(function () {
            Route::resource('sizes', SizeController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::resource('colors', ColorController::class)->only(['index', 'store', 'update', 'destroy']);
        });

        Route::resource('warehouses', WarehouseController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::prefix('inventory')->name('inventory.')->group(function () {
            // Route::get('stock', ...)->name('stock');
            // Route::get('serials', ...)->name('serials');
            // Route::get('movements', ...)->name('movements');
        });
    });
});

require __DIR__.'/settings.php';
