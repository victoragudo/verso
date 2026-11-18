<?php

use App\Verso\Core\Enums\Capability;
use App\Verso\Library\Http\Controllers\Admin\LibraryController;
use Illuminate\Support\Facades\Route;

// Design library: synced blocks and patterns people save from the editor.
Route::prefix('library')->name('library.')->middleware('can:'.Capability::ManageLibrary->value)->group(function () {
    Route::get('/', [LibraryController::class, 'index'])->name('index');
    Route::post('/', [LibraryController::class, 'store'])->name('store');
    Route::get('export', [LibraryController::class, 'export'])->name('export');
    Route::post('import', [LibraryController::class, 'import'])->name('import');
    Route::get('{item}', [LibraryController::class, 'edit'])->name('edit')->whereNumber('item');
    Route::put('{item}', [LibraryController::class, 'update'])->name('update')->whereNumber('item');
    Route::delete('{item}', [LibraryController::class, 'destroy'])->name('destroy')->whereNumber('item');
});
