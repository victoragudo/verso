<?php

use App\Verso\Core\Enums\Capability;
use App\Verso\Users\Http\Controllers\Admin\UserController;
use App\Verso\Users\Http\Controllers\Admin\UserInvitationController;
use Illuminate\Support\Facades\Route;

// Each route names its capability; the policies check the user being changed.
Route::prefix('users')->name('users.')->group(function () {
    Route::get('/', [UserController::class, 'index'])->name('index')->middleware('can:'.Capability::ListUsers->value);
    Route::post('/', [UserController::class, 'store'])->name('store')->middleware('can:'.Capability::CreateUsers->value);
    Route::post('invite', [UserInvitationController::class, 'store'])->name('invite')->middleware('can:'.Capability::CreateUsers->value);
    Route::post('{user}/invitation', [UserInvitationController::class, 'resend'])->name('invitation.resend')->middleware('can:'.Capability::CreateUsers->value);
    Route::put('{user}/role', [UserController::class, 'updateRole'])->name('role')->middleware('can:'.Capability::PromoteUsers->value);
    Route::delete('{user}', [UserController::class, 'destroy'])->name('destroy')->middleware('can:'.Capability::DeleteUsers->value);
});
