<?php

use App\Verso\Editor\Http\Controllers\BlockAssetController;
use App\Verso\Editor\Http\Controllers\CustomScriptController;
use Illuminate\Support\Facades\Route;

Route::get('verso-blocks/{asset}', BlockAssetController::class)
    ->where('asset', '[a-z0-9][a-z0-9-]{0,60}\.(css|js|json)')
    ->name('verso.block-asset');

Route::get('verso-code/{scope}.js', CustomScriptController::class)
    ->where('scope', 'site|entry-[0-9]+')
    ->name('verso.custom-script');
