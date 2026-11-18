<?php

use App\Verso\Core\Enums\Capability;
use Illuminate\Support\Facades\Route;
use Verso\Plugins\Shop\Http\Controllers\Admin\OrderController;
use Verso\Plugins\Shop\Http\Controllers\Admin\ProductController;
use Verso\Plugins\Shop\Http\Controllers\Admin\SettingsController;

// Shop admin pages, under /admin/shop.

Route::prefix('shop')->name('shop.')->middleware('can:'.Capability::ManageShop->value)->group(function () {
    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::get('products/{entry}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('products/{entry}', [ProductController::class, 'update'])->name('products.update');

    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])->whereNumber('order')->name('orders.show');
    Route::post('orders/{order}/status', [OrderController::class, 'updateStatus'])->whereNumber('order')->name('orders.status');

    Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
});
