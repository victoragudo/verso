<?php

use App\Verso\Core\Enums\Capability;
use App\Verso\Updates\Http\Controllers\Admin\UpdateController;
use Illuminate\Support\Facades\Route;

Route::prefix('updates')->name('updates.')->middleware('can:'.Capability::ManageUpdates->value)->group(function () {
    Route::get('/', [UpdateController::class, 'index'])->name('index');
    Route::post('check', [UpdateController::class, 'check'])->name('check');
    Route::post('/', [UpdateController::class, 'store'])->name('store');
    Route::put('settings', [UpdateController::class, 'settings'])->name('settings');
});
