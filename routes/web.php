<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('dashboard', '/'.config('verso.admin.prefix'))->name('dashboard');
});

require __DIR__.'/settings.php';
