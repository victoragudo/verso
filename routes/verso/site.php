<?php

use App\Verso\Core\Enums\Capability;
use App\Verso\Site\Http\Controllers\Admin\ChildThemeController;
use App\Verso\Site\Http\Controllers\Admin\MenuController;
use App\Verso\Site\Http\Controllers\Admin\PermalinksSeoController;
use App\Verso\Site\Http\Controllers\Admin\RedirectController;
use App\Verso\Site\Http\Controllers\Admin\SettingsController;
use App\Verso\Site\Http\Controllers\Admin\SiteEditorController;
use App\Verso\Site\Http\Controllers\Admin\TemplatePartsController;
use Illuminate\Support\Facades\Route;

Route::prefix('site')->name('site.')->group(function () {
    Route::middleware('can:'.Capability::ManageGeneralSettings->value)->group(function () {
        Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    });

    Route::middleware('can:'.Capability::ManagePermalinks->value)->group(function () {
        Route::get('permalinks', [PermalinksSeoController::class, 'edit'])->name('permalinks.edit');
        Route::put('permalinks', [PermalinksSeoController::class, 'updatePermalinks'])->name('permalinks.update');
        Route::put('permalinks/sitemap', [PermalinksSeoController::class, 'updateSitemap'])->name('permalinks.sitemap');
        Route::put('permalinks/search', [PermalinksSeoController::class, 'updateSearch'])->name('permalinks.search');

        Route::get('redirects', [RedirectController::class, 'index'])->name('redirects.index');
        Route::post('redirects', [RedirectController::class, 'store'])->name('redirects.store');
        Route::delete('redirects/{redirect}', [RedirectController::class, 'destroy'])->name('redirects.destroy');
    });

    Route::middleware('can:'.Capability::ManageMenus->value)->group(function () {
        Route::get('menus', [MenuController::class, 'index'])->name('menus.index');
        Route::get('menus/search', [MenuController::class, 'search'])->name('menus.search');
        Route::post('menus', [MenuController::class, 'store'])->name('menus.store');
        Route::put('menus/{menu}', [MenuController::class, 'update'])->name('menus.update');
        Route::put('menus/{menu}/items', [MenuController::class, 'items'])->name('menus.items');
        Route::delete('menus/{menu}', [MenuController::class, 'destroy'])->name('menus.destroy');
    });
});

Route::prefix('site-editor')->name('site-editor')->middleware('can:'.Capability::EditSite->value)->group(function () {
    Route::get('/', [SiteEditorController::class, 'index']);
    Route::get('preview', [SiteEditorController::class, 'preview'])->name('.preview');
    Route::get('templates', [SiteEditorController::class, 'templates'])->name('.templates');
    Route::post('child-theme', [ChildThemeController::class, 'store'])->name('.child-theme');
    Route::put('templates/{template}/parts', [TemplatePartsController::class, 'update'])->name('.templates.parts');
    Route::get('export', [SiteEditorController::class, 'export'])->name('.export');
    Route::post('import', [SiteEditorController::class, 'import'])->name('.import');
    Route::put('styles', [SiteEditorController::class, 'chooseStyle'])->name('.styles');
    Route::put('global-styles', [SiteEditorController::class, 'saveGlobalStyles'])->name('.global-styles');
    Route::get('{part}', [SiteEditorController::class, 'edit'])->name('.edit');
    Route::put('{part}', [SiteEditorController::class, 'update'])->name('.update');
    Route::post('{part}/publish', [SiteEditorController::class, 'publish'])->name('.publish');
    Route::delete('{part}', [SiteEditorController::class, 'destroy'])->name('.destroy');
});
