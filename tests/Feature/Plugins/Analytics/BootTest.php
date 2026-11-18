<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Support\AnalyticsPlugin;

beforeEach(fn () => AnalyticsPlugin::boot());

it('creates its tables when enabled', function () {
    expect(Schema::hasTable('analytics_events'))->toBeTrue()
        ->and(Schema::hasTable('analytics_daily'))->toBeTrue()
        ->and(Schema::hasTable('analytics_salts'))->toBeTrue();
});

it('registers the collector and admin routes', function () {
    expect(Route::has('analytics.collect'))->toBeTrue()
        ->and(Route::has('admin.analytics.index'))->toBeTrue()
        ->and(route('analytics.collect', [], false))->toBe('/_verso/collect');
});
