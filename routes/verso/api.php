<?php

use App\Verso\Api\Http\Controllers\EntryController;
use App\Verso\Api\Http\Controllers\MediaController;
use App\Verso\Api\Http\Controllers\TermController;
use Illuminate\Support\Facades\Route;

Route::get('entries/{contentType}', [EntryController::class, 'index'])->name('entries.index');
Route::get('entries/{contentType}/{slug}', [EntryController::class, 'show'])->name('entries.show')->where('slug', '[A-Za-z0-9\-_/]+');

Route::get('taxonomies/{taxonomy}/terms', [TermController::class, 'index'])->name('terms.index');
Route::get('taxonomies/{taxonomy}/terms/{slug}', [TermController::class, 'show'])->name('terms.show');

Route::get('media', [MediaController::class, 'index'])->name('media.index');
Route::get('media/{media}', [MediaController::class, 'show'])->name('media.show')->whereNumber('media');
