<?php

use App\Http\Controllers\Admin\OrganizationController as AdminOrganizationController;
use App\Http\Controllers\Catalog\ColorController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Catalog\ProductVariantController;
use App\Http\Controllers\Catalog\SizeController;
use App\Http\Controllers\CurrentOrganizationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Inventory\SerialNumberController;
use App\Http\Controllers\Inventory\StockMovementController;
use App\Http\Controllers\Inventory\StockOverviewController;
use App\Http\Controllers\InvitationAcceptanceController;
use App\Http\Controllers\NoOrganizationController;
use App\Http\Controllers\Operations\DispatchStockController;
use App\Http\Controllers\Operations\ReceiveStockController;
use App\Http\Controllers\Operations\ReturnStockController;
use App\Http\Controllers\Operations\TransferStockController;
use App\Http\Controllers\Organization\InvitationController;
use App\Http\Controllers\Organization\MemberController;
use App\Http\Controllers\Organization\SettingsController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::get('invitations/{token}', [InvitationAcceptanceController::class, 'show'])->name('invitations.show');
Route::post('invitations/{token}/register', [InvitationAcceptanceController::class, 'register'])
    ->middleware(['guest', 'throttle:6,1'])
    ->name('invitations.register');
Route::post('invitations/{token}/accept', [InvitationAcceptanceController::class, 'accept'])
    ->middleware(['auth', 'throttle:6,1'])
    ->name('invitations.accept');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::put('current-organization', [CurrentOrganizationController::class, 'update'])->name('current-organization.update');
    Route::get('no-organization', NoOrganizationController::class)->name('no-organization');

    // Platform administration: super-admins create and suspend organizations without belonging to them.
    Route::prefix('admin')->name('admin.')->middleware('can:super-admin')->group(function () {
        Route::resource('organizations', AdminOrganizationController::class)->only(['index', 'store', 'update']);
        Route::post('organizations/{organization}/admin-invitation', [AdminOrganizationController::class, 'inviteAdmin'])
            ->middleware('throttle:20,1')
            ->name('organizations.admin-invitation');
    });

    // Everything that shows organization data goes in here
    Route::middleware('organization')->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        // Everyone views the catalog and warehouses; managers and admins change them.
        Route::prefix('catalog')->name('catalog.')->group(function () {
            Route::resource('sizes', SizeController::class)->only(['index', 'store', 'update', 'destroy'])
                ->middlewareFor(['store', 'update', 'destroy'], 'role:manager');
            Route::resource('colors', ColorController::class)->only(['index', 'store', 'update', 'destroy'])
                ->middlewareFor(['store', 'update', 'destroy'], 'role:manager');
            Route::resource('products', ProductController::class)->only(['index', 'store', 'show', 'update', 'destroy'])
                ->middlewareFor(['store', 'update', 'destroy'], 'role:manager');
            Route::resource('products.variants', ProductVariantController::class)->only(['store', 'update', 'destroy'])->shallow()
                ->middleware('role:manager');
        });

        Route::resource('warehouses', WarehouseController::class)->only(['index', 'store', 'update', 'destroy'])
            ->middlewareFor(['store', 'update', 'destroy'], 'role:manager');

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

        // Everyone views the organization's settings and may leave it; only admins change settings.
        Route::prefix('organization/settings')->name('organization.settings.')->group(function () {
            Route::redirect('/', '/organization/settings/general')->name('index');
            Route::get('general', [SettingsController::class, 'editGeneral'])->name('general.edit');
            Route::put('general', [SettingsController::class, 'updateGeneral'])->middleware('role:admin')->name('general.update');
            Route::get('inventory', [SettingsController::class, 'editInventory'])->name('inventory.edit');
            Route::put('inventory', [SettingsController::class, 'updateInventory'])->middleware('role:admin')->name('inventory.update');
            Route::get('membership', [SettingsController::class, 'editMembership'])->name('membership.edit');
            Route::delete('membership', [SettingsController::class, 'leave'])->name('membership.destroy');
        });

        Route::prefix('organization')->name('organization.')->middleware('role:admin')->group(function () {
            Route::get('members', [MemberController::class, 'index'])->name('members.index');
            Route::put('members/{member}', [MemberController::class, 'update'])->name('members.update');
            Route::delete('members/{member}', [MemberController::class, 'destroy'])->name('members.destroy');
            Route::post('invitations', [InvitationController::class, 'store'])->middleware('throttle:20,1')->name('invitations.store');
            Route::post('invitations/{invitation}/resend', [InvitationController::class, 'resend'])->middleware('throttle:20,1')->name('invitations.resend');
            Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');
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
