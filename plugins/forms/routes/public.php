<?php

use Illuminate\Support\Facades\Route;
use Verso\Plugins\Forms\Http\Controllers\PublicController;

// Public form routes: the token for cached pages and the submit target.
Route::middleware('web')->prefix('forms')->name('forms.')->group(function () {
    Route::get('token', [PublicController::class, 'token'])->name('token');
    Route::post('{form:slug}', [PublicController::class, 'submit'])->middleware('throttle:forms-submit')->name('submit');
});
