<?php

use App\Verso\Access\Support\AccessAbilities;
use App\Verso\Layouts\Http\Controllers\Admin\LayoutController;
use Illuminate\Support\Facades\Route;

// Theme builder: templates, headers, footers and popups. Each layout also checks the capability of its kind.
Route::prefix('theme-builder')->name('theme-builder.')->middleware('can:'.AccessAbilities::LAYOUTS)->group(function () {
    Route::get('/', [LayoutController::class, 'index'])->name('index');
    Route::post('/', [LayoutController::class, 'store'])->name('store');
    Route::get('{layout}', [LayoutController::class, 'edit'])->name('edit');
    Route::put('{layout}', [LayoutController::class, 'update'])->name('update');
    Route::post('{layout}/publish', [LayoutController::class, 'publish'])->name('publish');
    Route::put('{layout}/switch', [LayoutController::class, 'toggle'])->name('switch');
    Route::delete('{layout}', [LayoutController::class, 'destroy'])->name('destroy');
    Route::get('{layout}/preview', [LayoutController::class, 'preview'])->name('preview');
    Route::post('{layout}/revisions/{revision}/restore', [LayoutController::class, 'restore'])->name('restore');
});
