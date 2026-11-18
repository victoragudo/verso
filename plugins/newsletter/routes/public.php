<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use Verso\Plugins\Newsletter\Http\Controllers\PublicController;

// Public newsletter routes: signup, double opt-in, unsubscribe and tracking.
Route::middleware('web')->prefix('newsletter')->name('newsletter.')->group(function () {
    Route::post('subscribe', [PublicController::class, 'subscribe'])->middleware('throttle:newsletter-signup')->name('subscribe');
    Route::get('confirm/{subscriber}', [PublicController::class, 'confirm'])->middleware(['signed', 'verso.theme', 'verso.footer'])->whereNumber('subscriber')->name('confirm');
    Route::match(['get', 'post'], 'unsubscribe/{subscriber}', [PublicController::class, 'unsubscribe'])
        ->middleware(['signed', 'verso.theme', 'verso.footer'])
        ->whereNumber('subscriber')
        ->withoutMiddleware([PreventRequestForgery::class, ValidateCsrfToken::class])
        ->name('unsubscribe');
    Route::get('o/{token}', [PublicController::class, 'open'])->name('open');
    Route::get('c/{token}/{link}', [PublicController::class, 'click'])->whereNumber('link')->name('click');
});
