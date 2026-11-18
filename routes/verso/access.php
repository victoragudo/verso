<?php

use App\Verso\Access\Http\Controllers\Admin\RoleController;
use App\Verso\Access\Http\Controllers\Admin\UserAccessController;
use App\Verso\Access\Http\Controllers\Admin\ViewAsController;
use App\Verso\Core\Enums\Capability;
use Illuminate\Support\Facades\Route;

Route::prefix('roles')->name('roles.')->middleware('can:'.Capability::ManageRoles->value)->group(function () {
    Route::get('/', [RoleController::class, 'index'])->name('index');
    Route::post('/', [RoleController::class, 'store'])->name('store');
    Route::put('matrix', [RoleController::class, 'updateMatrix'])->name('matrix');
    Route::get('compare', [RoleController::class, 'compare'])->name('compare');
    Route::get('{role}', [RoleController::class, 'show'])->name('show');
    Route::put('{role}', [RoleController::class, 'update'])->name('update');
    Route::delete('{role}', [RoleController::class, 'destroy'])->name('destroy');
    Route::post('{role}/clone', [RoleController::class, 'clone'])->name('clone');
    Route::post('{role}/reset', [RoleController::class, 'reset'])->name('reset');
    Route::post('{role}/view-as', [ViewAsController::class, 'start'])->name('view-as');
});

// Leaving the preview needs no capability: the previewed role may have none.
Route::delete('view-as', [ViewAsController::class, 'stop'])->name('view-as.stop');

Route::prefix('users/{user}/access')->name('users.access.')->middleware('can:'.Capability::PromoteUsers->value)->group(function () {
    Route::get('/', [UserAccessController::class, 'edit'])->name('edit');
    Route::put('/', [UserAccessController::class, 'update'])->name('update');
});
