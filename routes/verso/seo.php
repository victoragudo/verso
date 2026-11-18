<?php

use App\Verso\Core\Enums\Capability;
use App\Verso\Seo\Http\Controllers\Admin\AltTextController;
use App\Verso\Seo\Http\Controllers\Admin\NotFoundController;
use App\Verso\Seo\Http\Controllers\Admin\SeoOverviewController;
use Illuminate\Support\Facades\Route;

Route::prefix('seo')->name('seo.')->group(function () {
    Route::middleware('can:'.Capability::ViewSeo->value)->group(function () {
        Route::get('/', [SeoOverviewController::class, 'index'])->name('index');
        Route::get('issues/{check}', [SeoOverviewController::class, 'show'])->name('issues.show');
        Route::get('not-found', [NotFoundController::class, 'index'])->name('not-found.index');
    });

    Route::middleware('can:'.Capability::ManageSeo->value)->group(function () {
        Route::post('refresh', [SeoOverviewController::class, 'refresh'])->name('refresh');
        Route::post('issues/{check}/fix', [SeoOverviewController::class, 'fix'])->name('issues.fix');
        Route::post('link-check', [SeoOverviewController::class, 'checkLinks'])->name('link-check');

        Route::post('not-found/{hit}/redirect', [NotFoundController::class, 'redirect'])->name('not-found.redirect');
        Route::delete('not-found/{hit}', [NotFoundController::class, 'destroy'])->name('not-found.destroy');
        Route::delete('not-found', [NotFoundController::class, 'clear'])->name('not-found.clear');

        Route::get('alt-text', [AltTextController::class, 'index'])->name('alt-text.index');
        Route::put('alt-text', [AltTextController::class, 'update'])->name('alt-text.update');
    });
});
