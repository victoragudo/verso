<?php

use Illuminate\Support\Facades\Route;
use Verso\Plugins\Shop\Http\Controllers\CartController;
use Verso\Plugins\Shop\Http\Controllers\CheckoutController;
use Verso\Plugins\Shop\Http\Controllers\OrderController;
use Verso\Plugins\Shop\Http\Controllers\ShopController;

// Public shop pages. Product pages are served by the theme at "/{product_base}/{slug}".

$shop = trim((string) config('shop.routes.shop'), '/');
$cart = trim((string) config('shop.routes.cart'), '/');
$checkout = trim((string) config('shop.routes.checkout'), '/');

Route::get($shop, ShopController::class)->name('theme.shop.index');

Route::get($cart, [CartController::class, 'show'])->name('theme.shop.cart');
Route::post($cart, [CartController::class, 'store'])->name('theme.shop.cart.add');
Route::patch($cart, [CartController::class, 'update'])->name('theme.shop.cart.update');
Route::delete($cart.'/{line}', [CartController::class, 'destroy'])
    ->where('line', 'p\d+(-v\d+)?')
    ->name('theme.shop.cart.remove');

Route::get($checkout, [CheckoutController::class, 'show'])->name('theme.shop.checkout');
Route::post($checkout, [CheckoutController::class, 'store'])
    ->middleware('throttle:shop-checkout')
    ->name('theme.shop.checkout.store');

Route::get($checkout.'/orders/{token}', [OrderController::class, 'show'])
    ->whereUuid('token')
    ->name('theme.shop.order');
Route::post($checkout.'/orders/{token}/pay', [OrderController::class, 'pay'])
    ->whereUuid('token')
    ->middleware('throttle:shop-checkout')
    ->name('theme.shop.order.pay');
