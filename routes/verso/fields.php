<?php

use App\Verso\Content\Models\Entry;
use App\Verso\Core\Enums\Capability;
use App\Verso\Fields\Http\Controllers\Admin\FieldGroupController;
use App\Verso\Fields\Http\Controllers\Admin\FieldLookupController;
use Illuminate\Support\Facades\Route;

Route::get('fields/lookup', FieldLookupController::class)->name('fields.lookup')->middleware('can:viewAny,'.Entry::class);

Route::prefix('fields')->name('fields.')->middleware('can:'.Capability::ManageFields->value)->group(function () {
    Route::get('/', [FieldGroupController::class, 'index'])->name('index');
    Route::get('create', [FieldGroupController::class, 'create'])->name('create');
    Route::post('/', [FieldGroupController::class, 'store'])->name('store');
    Route::get('export', [FieldGroupController::class, 'export'])->name('export');
    Route::post('import', [FieldGroupController::class, 'import'])->name('import');
    Route::post('copy/{key}', [FieldGroupController::class, 'copy'])->name('copy')->where('key', '[a-z][a-z0-9_\-]*');
    Route::get('{fieldGroup}/edit', [FieldGroupController::class, 'edit'])->name('edit')->whereNumber('fieldGroup');
    Route::put('{fieldGroup}', [FieldGroupController::class, 'update'])->name('update')->whereNumber('fieldGroup');
    Route::delete('{fieldGroup}', [FieldGroupController::class, 'destroy'])->name('destroy')->whereNumber('fieldGroup');
});
