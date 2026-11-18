<?php

use App\Verso\I18n\Http\Controllers\LocaleController;
use App\Verso\I18n\Http\Controllers\TranslationBundleController;
use Illuminate\Support\Facades\Route;

// Admin strings of one language. No session, so browsers and proxies can cache it.
Route::get('i18n/{locale}/{version}.json', TranslationBundleController::class)
    ->where(['locale' => '[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})*', 'version' => '[a-f0-9]{1,32}'])
    ->name('verso.i18n.bundle');

// The admin language picked in the user menu, the profile page, the login screen or the installer.
Route::middleware('web')
    ->put('locale', [LocaleController::class, 'update'])
    ->name('verso.locale.update');
