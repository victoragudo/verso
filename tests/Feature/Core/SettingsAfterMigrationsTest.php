<?php

use App\Verso\Core\Settings\Settings;
use Illuminate\Database\Events\DatabaseRefreshed;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\DB;

it('forgets cached settings when the database is refreshed', function () {
    $settings = app(Settings::class);
    $settings->set('site.name', 'Harbour Notes');
    expect($settings->get('site.name'))->toBe('Harbour Notes');

    DB::table('settings')->where('key', 'site.name')->delete();
    event(new DatabaseRefreshed);

    expect($settings->get('site.name'))->not->toBe('Harbour Notes');
});

it('forgets cached plugin settings after migrations run', function () {
    $settings = app(Settings::class);
    $settings->set('plugins.enabled', ['reading-time']);

    DB::table('settings')->where('key', 'plugins.enabled')->delete();
    event(new MigrationsEnded('up'));

    expect($settings->get('plugins.enabled', []))->toBe([]);
});
