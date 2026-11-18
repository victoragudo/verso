<?php

use Illuminate\Support\Facades\Route;
use Verso\Plugins\Analytics\Http\Controllers\Admin\AnalyticsController;

// Admin pages of the Analytics plugin.
Route::prefix('analytics')->name('analytics.')->middleware('can:'.config('analytics.capability'))->group(function () {
    Route::get('/', [AnalyticsController::class, 'index'])->name('index');
    Route::put('settings', [AnalyticsController::class, 'updateSettings'])->name('settings.update')->middleware('can:manage_settings');
});
