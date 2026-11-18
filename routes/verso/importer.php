<?php

use App\Verso\Importer\Http\Controllers\Admin\ImportController;
use Illuminate\Support\Facades\Route;

Route::prefix('import')->name('import.')->middleware('can:import_content')->group(function () {
    Route::get('/', [ImportController::class, 'index'])->name('index');
    Route::post('/', [ImportController::class, 'store'])->name('store');
    Route::get('{import}', [ImportController::class, 'show'])->name('show');
});
