<?php

use Illuminate\Support\Facades\Route;
use Verso\Plugins\Analytics\AnalyticsServiceProvider;
use Verso\Plugins\Analytics\Http\Controllers\CollectController;

// The beacon endpoint. No session and no cookies: it only uses the throttle middleware.
Route::post(trim((string) config('analytics.endpoint'), '/'), CollectController::class)
    ->middleware('throttle:'.AnalyticsServiceProvider::RATE_LIMITER)
    ->name('analytics.collect');
