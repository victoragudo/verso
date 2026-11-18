<?php

use App\Verso\Core\Enums\Capability;
use App\Verso\System\Http\Controllers\Admin\ServerHealthController;
use App\Verso\System\Http\Controllers\Admin\SystemSettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('system')->name('system.')->middleware('can:'.Capability::ManageSystem->value)->group(function () {
    Route::get('settings', [SystemSettingsController::class, 'index'])->name('settings.index');
    Route::put('settings', [SystemSettingsController::class, 'update'])->name('settings.update');
    Route::post('settings/reset', [SystemSettingsController::class, 'reset'])->name('settings.reset');
    Route::post('settings/php-limits', [SystemSettingsController::class, 'applyIni'])->name('settings.php-limits');

    Route::get('health', [ServerHealthController::class, 'index'])->name('health.index');
    Route::post('health/fix', [ServerHealthController::class, 'fix'])->name('health.fix');
});
