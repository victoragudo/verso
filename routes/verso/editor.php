<?php

use App\Verso\Access\Support\AccessAbilities;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Enums\Capability;
use App\Verso\Editor\Http\Controllers\Admin\ConvertHtmlController;
use App\Verso\Editor\Http\Controllers\Admin\CustomCodeController;
use App\Verso\Editor\Http\Controllers\Admin\EditorSettingsController;
use App\Verso\Editor\Http\Controllers\Admin\StylePresetController;
use Illuminate\Support\Facades\Route;

Route::prefix('editor')->name('editor.')->group(function () {
    Route::middleware('can:'.Capability::ManageEditorSettings->value)->group(function () {
        Route::get('settings', [EditorSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [EditorSettingsController::class, 'update'])->name('settings.update');
    });
    Route::middleware('can:'.Capability::EditStyles->value)->group(function () {
        Route::get('presets', [StylePresetController::class, 'index'])->name('presets.index');
        Route::post('presets', [StylePresetController::class, 'store'])->name('presets.store');
        Route::put('presets/{preset}', [StylePresetController::class, 'update'])->name('presets.update');
        Route::delete('presets/{preset}', [StylePresetController::class, 'destroy'])->name('presets.destroy');
    });
    Route::post('convert', ConvertHtmlController::class)->name('convert')->middleware('can:viewAny,'.Entry::class);
    Route::middleware('can:'.AccessAbilities::CUSTOM_CODE)->group(function () {
        Route::get('code', [CustomCodeController::class, 'edit'])->name('code.edit');
        Route::put('code', [CustomCodeController::class, 'update'])->name('code.update');
    });
    Route::middleware('can:update,entry')->group(function () {
        Route::get('code/entries/{entry}', [CustomCodeController::class, 'showEntry'])->name('code.entry');
        Route::put('code/entries/{entry}', [CustomCodeController::class, 'updateEntry'])->name('code.entry.update');
    });
});
