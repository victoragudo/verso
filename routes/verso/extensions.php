<?php

use App\Verso\Access\Support\AccessAbilities;
use App\Verso\Core\Enums\Capability;
use App\Verso\Extensions\Http\Controllers\Admin\ExtensionController;
use Illuminate\Support\Facades\Route;

Route::prefix('extensions')->name('extensions.')->group(function () {
    Route::put('settings', [ExtensionController::class, 'updateSettings'])->name('settings')->middleware('can:'.Capability::ManageSettings->value);
});

// Installing and removing plugins or themes. The controller checks the kind (install_plugins or manage_themes).
Route::prefix('extensions')->name('extensions.')->middleware('can:'.AccessAbilities::INSTALL_EXTENSIONS)->group(function () {
    Route::get('/', [ExtensionController::class, 'index'])->name('index');
    Route::post('upload', [ExtensionController::class, 'upload'])->name('upload');
    Route::post('registry/{kind}/{slug}', [ExtensionController::class, 'stageFromRegistry'])->name('registry');
    Route::get('review/{token}', [ExtensionController::class, 'review'])->name('review');
    Route::post('review/{token}', [ExtensionController::class, 'install'])->name('install');
    Route::delete('review/{token}', [ExtensionController::class, 'discard'])->name('discard');
    Route::delete('{kind}/{slug}', [ExtensionController::class, 'remove'])->name('remove');
});
