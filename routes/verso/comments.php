<?php

use App\Verso\Comments\Http\Controllers\Admin\CommentBulkController;
use App\Verso\Comments\Http\Controllers\Admin\CommentController;
use App\Verso\Core\Enums\Capability;
use Illuminate\Support\Facades\Route;

Route::prefix('comments')->name('comments.')->middleware('can:'.Capability::ModerateComments->value)->group(function () {
    Route::get('/', [CommentController::class, 'index'])->name('index');
    Route::post('bulk', CommentBulkController::class)->name('bulk');
});
