<?php

use App\Verso\Core\Enums\Capability;
use App\Verso\Media\Http\Controllers\Admin\MediaController;
use App\Verso\Media\Http\Controllers\Admin\MediaLibraryController;
use Illuminate\Support\Facades\Route;

Route::prefix('media')->name('media.')->middleware('can:'.Capability::UploadFiles->value)->group(function () {
    Route::get('/', [MediaController::class, 'index'])->name('index');
    Route::get('library', MediaLibraryController::class)->name('library');
    Route::post('/', [MediaController::class, 'store'])->name('store');
    Route::get('{media}', [MediaController::class, 'show'])->whereNumber('media')->name('show');
    Route::put('{media}', [MediaController::class, 'update'])->whereNumber('media')->name('update');
    Route::delete('{media}', [MediaController::class, 'destroy'])->whereNumber('media')->name('destroy');
});
