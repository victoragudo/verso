<?php

use App\Verso\Core\Enums\Capability;
use App\Verso\Theme\Http\Controllers\Admin\ThemeController;
use Illuminate\Support\Facades\Route;

Route::prefix('themes')->name('themes.')->middleware('can:'.Capability::ManageThemes->value)->group(function () {
    Route::get('/', [ThemeController::class, 'index'])->name('index');
    Route::post('cache/clear', [ThemeController::class, 'clearCache'])->name('cache.clear');
    Route::get('{theme}', [ThemeController::class, 'show'])->name('show');
    Route::post('{theme}/verify', [ThemeController::class, 'verify'])->name('verify');
    Route::post('{theme}/activate', [ThemeController::class, 'activate'])->name('activate');
    Route::get('{theme}/customize', [ThemeController::class, 'customize'])->name('customize');
    Route::put('{theme}/customize', [ThemeController::class, 'saveCustomization'])->name('customize.update');
    Route::get('{theme}/preview', [ThemeController::class, 'preview'])->name('preview');
});
