<?php

use App\Verso\Theme\Http\Controllers\AuthorController;
use App\Verso\Theme\Http\Controllers\FeedController;
use App\Verso\Theme\Http\Controllers\HomeController;
use App\Verso\Theme\Http\Controllers\PublicPathController;
use App\Verso\Theme\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

// Public site routes. The order matters: the catch-all must stay last.
// Archives, terms and entries all go through the catch-all, because their URL
// structure comes from the permalink settings and can change at any time.

Route::get('/', HomeController::class)->name('home');
Route::get('search', SearchController::class)->name('theme.search');
Route::get('feed', FeedController::class)->name('theme.feed');
Route::get(trim((string) config('verso.users.profile.author_base'), '/').'/{author}', AuthorController::class)
    ->where('author', '[a-z0-9-]+')
    ->name('theme.author');

Route::get('{path}', PublicPathController::class)
    ->where('path', '.+')
    ->fallback()
    ->name('theme.page');
