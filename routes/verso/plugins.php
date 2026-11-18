<?php

use App\Verso\Core\Enums\Capability;
use App\Verso\Plugins\Http\Controllers\Admin\PluginController;
use Illuminate\Support\Facades\Route;

Route::prefix('plugins')->name('plugins.')->middleware('can:'.Capability::ActivatePlugins->value)->group(function () {
    Route::get('/', [PluginController::class, 'index'])->name('index');
    Route::get('{plugin}', [PluginController::class, 'show'])->name('show');
    Route::post('{plugin}/verify', [PluginController::class, 'verify'])->name('verify');
    Route::post('{plugin}/enable', [PluginController::class, 'enable'])->name('enable');
    Route::post('{plugin}/disable', [PluginController::class, 'disable'])->name('disable');
});
