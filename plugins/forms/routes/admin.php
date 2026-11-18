<?php

use Illuminate\Support\Facades\Route;
use Verso\Plugins\Forms\Http\Controllers\Admin\FormController;
use Verso\Plugins\Forms\Http\Controllers\Admin\SettingsController;
use Verso\Plugins\Forms\Http\Controllers\Admin\SubmissionController;

// Admin pages of the Forms plugin, under /admin/forms. /admin/plugins/{slug} belongs to the plugin detail page.
Route::prefix('forms')->name('forms.')->group(function () {
    Route::middleware('can:'.config('forms.capability'))->group(function () {
        Route::get('/', [FormController::class, 'index'])->name('index');
        Route::get('create', [FormController::class, 'create'])->name('create');
        Route::post('/', [FormController::class, 'store'])->name('store');
        Route::get('{form}/edit', [FormController::class, 'edit'])->whereNumber('form')->name('edit');
        Route::put('{form}', [FormController::class, 'update'])->whereNumber('form')->name('update');
        Route::delete('{form}', [FormController::class, 'destroy'])->whereNumber('form')->name('destroy');
    });

    Route::middleware('can:'.config('forms.messages_capability'))->group(function () {
        Route::get('submissions', [SubmissionController::class, 'index'])->name('submissions.index');
        Route::get('submissions/export', [SubmissionController::class, 'export'])->name('submissions.export');
        Route::post('submissions/bulk', [SubmissionController::class, 'bulk'])->name('submissions.bulk');
        Route::post('submissions/erase', [SubmissionController::class, 'erase'])->name('submissions.erase');
        Route::get('submissions/{submission}', [SubmissionController::class, 'show'])->whereNumber('submission')->name('submissions.show');
        Route::put('submissions/{submission}/status', [SubmissionController::class, 'status'])->whereNumber('submission')->name('submissions.status');
        Route::delete('submissions/{submission}', [SubmissionController::class, 'destroy'])->whereNumber('submission')->name('submissions.destroy');
        Route::get('submissions/{submission}/files/{field}', [SubmissionController::class, 'file'])->whereNumber('submission')->where('field', '[a-z][a-z0-9_]*')->name('submissions.file');
    });

    Route::middleware('can:manage_settings')->group(function () {
        Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    });
});
