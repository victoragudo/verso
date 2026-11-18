<?php

use App\Verso\Install\Http\Controllers\ConnectionTestController;
use App\Verso\Install\Http\Controllers\InstallRunController;
use App\Verso\Install\Http\Controllers\WizardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [WizardController::class, 'index'])->name('show');

Route::middleware('throttle:verso-install-tests')->group(function (): void {
    Route::post('test/database', [ConnectionTestController::class, 'database'])->name('test.database');
    Route::post('test/database/create', [ConnectionTestController::class, 'createDatabase'])->name('test.create-database');
    Route::post('test/mail', [ConnectionTestController::class, 'mail'])->name('test.mail');
    Route::post('test/redis', [ConnectionTestController::class, 'redis'])->name('test.redis');
    Route::post('test/storage', [ConnectionTestController::class, 'storage'])->name('test.storage');
});

Route::post('run', [InstallRunController::class, 'run'])->name('run');
Route::get('status', [InstallRunController::class, 'status'])->name('status');

Route::get('{step}', [WizardController::class, 'show'])->name('step');
Route::post('{step}', [WizardController::class, 'save'])->name('save');
