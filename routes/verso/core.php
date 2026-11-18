<?php

use App\Verso\Core\Http\Controllers\Admin\DashboardController;
use App\Verso\Core\Http\Controllers\Admin\ListViewController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');

// Columns and density of an admin list, saved per user.
Route::put('list-views/{list}', [ListViewController::class, 'update'])
    ->where('list', '[a-z0-9][a-z0-9._-]{0,79}')
    ->name('list-views.update');
