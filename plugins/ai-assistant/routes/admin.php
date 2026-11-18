<?php

use Illuminate\Support\Facades\Route;
use Verso\Plugins\AiAssistant\Http\Controllers\AiActionController;
use Verso\Plugins\AiAssistant\Http\Controllers\AiAdminController;

Route::prefix('ai-assistant')->name('ai-assistant.')->group(function () {
    Route::middleware('can:ai.use')->prefix('actions')->name('actions.')->group(function () {
        Route::post('outline', [AiActionController::class, 'outline'])->name('outline');
        Route::post('rewrite', [AiActionController::class, 'rewrite'])->name('rewrite');
        Route::post('excerpt', [AiActionController::class, 'excerpt'])->name('excerpt');
        Route::post('seo', [AiActionController::class, 'seo'])->name('seo');
        Route::post('alt-text', [AiActionController::class, 'altText'])->name('alt-text');
        Route::post('translate', [AiActionController::class, 'translate'])->name('translate');
        Route::post('section-text', [AiActionController::class, 'sectionText'])->name('section-text');
        Route::post('headlines', [AiActionController::class, 'headlines'])->name('headlines');
    });

    Route::put('business', [AiActionController::class, 'saveBusiness'])->middleware('can:ai.use')->name('business.update');

    Route::middleware('can:manage_settings')->group(function () {
        Route::get('/', [AiAdminController::class, 'index'])->name('index');
        Route::put('settings', [AiAdminController::class, 'update'])->name('settings.update');
        Route::delete('settings/key', [AiAdminController::class, 'forgetKey'])->name('settings.forget-key');
    });
});
