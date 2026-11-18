<?php

use App\Verso\Content\Http\Controllers\Admin\EntryBulkController;
use App\Verso\Content\Http\Controllers\Admin\EntryController;
use App\Verso\Content\Http\Controllers\Admin\EntryRevisionCompareController;
use App\Verso\Content\Http\Controllers\Admin\EntryRevisionController;
use App\Verso\Content\Http\Controllers\Admin\TermController;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Enums\Capability;
use Illuminate\Support\Facades\Route;

Route::prefix('entries/{contentType}')->name('entries.')->middleware('can:viewAny,'.Entry::class.',contentType')->group(function () {
    Route::get('/', [EntryController::class, 'index'])->name('index');
    Route::get('create', [EntryController::class, 'create'])->name('create');
    Route::post('/', [EntryController::class, 'store'])->name('store');
    Route::post('bulk', EntryBulkController::class)->name('bulk');
    Route::get('{entry}/edit', [EntryController::class, 'edit'])->name('edit');
    Route::put('{entry}', [EntryController::class, 'update'])->name('update');
    Route::delete('{entry}', [EntryController::class, 'destroy'])->name('destroy');
    Route::post('{entry}/restore', [EntryController::class, 'restore'])->name('restore')->withTrashed();
    Route::post('{entry}/revisions/{revision}/restore', EntryRevisionController::class)->name('revisions.restore');
    Route::get('{entry}/revisions/{revision}/compare', EntryRevisionCompareController::class)->name('revisions.compare');
});

Route::prefix('terms/{taxonomy}')->name('terms.')->middleware('can:'.Capability::ManageTerms->value)->group(function () {
    Route::get('/', [TermController::class, 'index'])->name('index');
    Route::post('/', [TermController::class, 'store'])->name('store');
    Route::put('{term}', [TermController::class, 'update'])->name('update');
    Route::delete('{term}', [TermController::class, 'destroy'])->name('destroy');
});
