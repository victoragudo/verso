<?php

use App\Verso\Core\Settings\Settings;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Route::middleware('web')->get('/_locale-probe', fn () => app()->getLocale());
});

it('uses the site locale setting for web requests', function () {
    app(Settings::class)->set('site.locale', 'pt');

    $this->get('/_locale-probe')->assertOk()->assertContent('pt');
});

it('ignores locales that are not in the allowed list', function () {
    config(['app.locale' => 'en']);
    app(Settings::class)->set('site.locale', 'klingon');

    $this->get('/_locale-probe')->assertOk()->assertContent('en');
});

it('keeps the default locale before the settings table exists', function () {
    config(['app.locale' => 'en']);
    app(Settings::class)->forgetCache();
    Schema::drop('settings');

    $this->get('/_locale-probe')->assertOk()->assertContent('en');
});
