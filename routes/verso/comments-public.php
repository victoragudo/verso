<?php

use App\Verso\Comments\Http\Controllers\CommentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'throttle:verso-comments'])
    ->post('comments/{entry}', [CommentController::class, 'store'])
    ->whereNumber('entry')
    ->name('comments.store');

Route::middleware('web')
    ->get('comments/token', [CommentController::class, 'token'])
    ->name('comments.token');
