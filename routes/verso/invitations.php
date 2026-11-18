<?php

use App\Verso\Users\Http\Controllers\AcceptInvitationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signed'])->prefix('invitation')->name('invitation.')->group(function () {
    Route::get('{user}/{token}', [AcceptInvitationController::class, 'show'])->name('show');
    Route::post('{user}/{token}', [AcceptInvitationController::class, 'store'])->name('accept');
});
