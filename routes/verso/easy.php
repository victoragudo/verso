<?php

use App\Verso\Content\Models\Entry;
use App\Verso\Core\Enums\Capability;
use App\Verso\Easy\Http\Controllers\Admin\EasyStyleController;
use App\Verso\Easy\Http\Controllers\Admin\GuidedStartController;
use App\Verso\Easy\Http\Controllers\Admin\HelpController;
use App\Verso\Easy\Http\Controllers\Admin\LaunchController;
use App\Verso\Easy\Http\Controllers\Admin\PageEditorController;
use App\Verso\Easy\Http\Controllers\Admin\SectionController;
use App\Verso\Easy\Http\Controllers\Admin\SimpleModeController;
use App\Verso\Easy\Http\Controllers\Admin\SiteDetailsController;
use Illuminate\Support\Facades\Route;

Route::prefix('easy')->name('easy.')->group(function () {
    Route::middleware('can:'.Capability::ManageSettings->value)->group(function () {
        Route::get('start', [GuidedStartController::class, 'show'])->name('start');
        Route::post('start', [GuidedStartController::class, 'store'])->name('start.store');

        Route::get('details', [SiteDetailsController::class, 'show'])->name('details');
        Route::put('details', [SiteDetailsController::class, 'update'])->name('details.update');

        // The buttons of the launch checklist on the dashboard.
        Route::post('launch/privacy', [LaunchController::class, 'privacy'])->name('launch.privacy');
        Route::post('launch/go-live', [LaunchController::class, 'goLive'])->name('launch.go-live');
        Route::post('launch/dismiss', [LaunchController::class, 'dismiss'])->name('launch.dismiss');
    });

    Route::middleware('can:'.Capability::ManageThemes->value)->group(function () {
        Route::get('style', [EasyStyleController::class, 'show'])->name('style');
        Route::put('style', [EasyStyleController::class, 'update'])->name('style.update');
    });

    // Simple mode and the help panel are there for everyone who writes or builds the site.
    Route::middleware('can:'.Capability::EditOwnEntries->value)->group(function () {
        Route::put('simple-mode', [SimpleModeController::class, 'update'])->name('simple-mode.update');
        Route::get('help', [HelpController::class, 'index'])->name('help');
    });

    // The visual page editor. Each action checks that the user may change the entry.
    Route::middleware('can:update,entry')->group(function () {
        Route::get('pages/{entry}', [PageEditorController::class, 'edit'])->name('pages.edit');
        Route::post('pages/{entry}/frame', [PageEditorController::class, 'frame'])->name('pages.frame');
        Route::put('pages/{entry}', [PageEditorController::class, 'update'])->name('pages.update');
        Route::put('pages/{entry}/draft', [PageEditorController::class, 'saveDraft'])->name('pages.draft');
        Route::delete('pages/{entry}/draft', [PageEditorController::class, 'discardDraft'])->name('pages.draft.discard');
        Route::get('pages/{entry}/versions', [PageEditorController::class, 'versions'])->name('pages.versions');
    });

    Route::get('sections/{key}', [SectionController::class, 'build'])
        ->middleware('can:create,'.Entry::class)
        ->name('sections.build')
        ->where('key', '[a-z][a-z0-9-]*');
});
