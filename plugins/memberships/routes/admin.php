<?php

use Illuminate\Support\Facades\Route;
use Verso\Plugins\Memberships\Http\Controllers\MemberController;
use Verso\Plugins\Memberships\Http\Controllers\PlanController;

// Admin pages of the Memberships plugin, under /admin/plugins/memberships.
Route::prefix('plugins/memberships')->name('memberships.')->middleware('can:memberships.manage')->group(function () {
    Route::get('members', [MemberController::class, 'index'])->name('members.index');
    Route::post('members', [MemberController::class, 'store'])->name('members.store');
    Route::put('members/{membership}', [MemberController::class, 'update'])->name('members.update');
    Route::delete('members/{membership}', [MemberController::class, 'destroy'])->name('members.destroy');

    Route::get('plans', [PlanController::class, 'index'])->name('plans.index');
    Route::post('plans', [PlanController::class, 'store'])->name('plans.store');
    Route::put('plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
    Route::delete('plans/{plan}', [PlanController::class, 'destroy'])->name('plans.destroy');
    Route::put('settings', [PlanController::class, 'settings'])->name('settings.update');
});
