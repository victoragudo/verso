<?php

use Illuminate\Support\Facades\Route;
use Verso\Plugins\Newsletter\Http\Controllers\Admin\CampaignController;
use Verso\Plugins\Newsletter\Http\Controllers\Admin\SettingsController;
use Verso\Plugins\Newsletter\Http\Controllers\Admin\SubscriberController;

// Admin pages of the Newsletter plugin, under /admin/plugins/newsletter.
Route::prefix('plugins/newsletter')->name('newsletter.')->group(function () {
    Route::middleware('can:newsletter.manage')->group(function () {
        Route::get('subscribers', [SubscriberController::class, 'index'])->name('subscribers.index');
        Route::post('subscribers', [SubscriberController::class, 'store'])->name('subscribers.store');
        Route::get('subscribers/export', [SubscriberController::class, 'export'])->name('subscribers.export');
        Route::post('subscribers/import', [SubscriberController::class, 'import'])->name('subscribers.import');
        Route::put('subscribers/{subscriber}/unsubscribe', [SubscriberController::class, 'unsubscribe'])->name('subscribers.unsubscribe');
        Route::delete('subscribers/{subscriber}', [SubscriberController::class, 'destroy'])->name('subscribers.destroy');
        Route::post('lists', [SubscriberController::class, 'storeList'])->name('lists.store');
        Route::delete('lists/{list}', [SubscriberController::class, 'destroyList'])->name('lists.destroy');

        Route::get('campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
        Route::get('campaigns/create', [CampaignController::class, 'create'])->name('campaigns.create');
        Route::post('campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
        Route::get('campaigns/{campaign}', [CampaignController::class, 'show'])->name('campaigns.show');
        Route::get('campaigns/{campaign}/edit', [CampaignController::class, 'edit'])->name('campaigns.edit');
        Route::put('campaigns/{campaign}', [CampaignController::class, 'update'])->name('campaigns.update');
        Route::delete('campaigns/{campaign}', [CampaignController::class, 'destroy'])->name('campaigns.destroy');
        Route::post('campaigns/{campaign}/test', [CampaignController::class, 'test'])->name('campaigns.test');
        Route::post('campaigns/{campaign}/send', [CampaignController::class, 'send'])->name('campaigns.send');
    });

    Route::middleware('can:manage_settings')->group(function () {
        Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    });
});
