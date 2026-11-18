<?php

use App\Verso\Core\Models\Setting;
use App\Verso\Core\Settings\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->settings = app(Settings::class);
});

it('is a singleton', function () {
    expect(app(Settings::class))->toBe($this->settings);
});

it('stores and reads values of any JSON type', function () {
    $this->settings->set('site.name', 'Cuaderno de Viajes');
    $this->settings->set('reading.posts_per_page', 8);
    $this->settings->set('plugins.enabled', ['seo-tools', 'contact-form']);

    expect($this->settings->get('site.name'))->toBe('Cuaderno de Viajes')
        ->and($this->settings->get('reading.posts_per_page'))->toBe(8)
        ->and($this->settings->get('plugins.enabled'))->toBe(['seo-tools', 'contact-form'])
        ->and(Setting::where('key', 'site.name')->exists())->toBeTrue();
});

it('falls back to the given default and then to the config default', function () {
    config(['verso.settings.defaults' => ['site.tagline' => 'Stories from the road']]);
    app()->forgetInstance(Settings::class);
    $settings = app(Settings::class);

    expect($settings->get('site.tagline'))->toBe('Stories from the road')
        ->and($settings->get('site.tagline', 'Notes from Lisbon'))->toBe('Notes from Lisbon')
        ->and($settings->get('newsletter.provider'))->toBeNull();
});

it('uses the revisions limit default from config', function () {
    expect($this->settings->get('content.revisions_limit'))->toBe(25);
});

it('returns a stored null instead of the default', function () {
    $this->settings->set('reading.front_page', null);

    expect($this->settings->has('reading.front_page'))->toBeTrue()
        ->and($this->settings->get('reading.front_page', 5))->toBeNull();
});

it('reads from memory after the first load', function () {
    $this->settings->set('site.name', 'Huerto Urbano');
    $this->settings->get('site.name');

    DB::enableQueryLog();
    $this->settings->get('site.name');
    $this->settings->get('site.tagline');

    expect(DB::getQueryLog())->toBe([]);
});

it('keeps settings in the cache store', function () {
    $this->settings->set('site.timezone', 'Europe/Madrid');
    $this->settings->get('site.timezone');

    expect(Cache::get(config('verso.settings.cache_key')))->toBe(['site.timezone' => 'Europe/Madrid']);
});

it('clears the cache when a value changes', function () {
    $this->settings->set('site.locale', 'es');
    $this->settings->get('site.locale');

    $this->settings->set('site.locale', 'pt');

    expect($this->settings->get('site.locale'))->toBe('pt');
});

it('saves many values at once', function () {
    $this->settings->setMany(['site.name' => 'La Bodega', 'site.tagline' => 'Wines from Rioja']);

    expect($this->settings->all())->toBe(['site.name' => 'La Bodega', 'site.tagline' => 'Wines from Rioja']);
});

it('forgets a value', function () {
    $this->settings->set('theme.active', 'aurora');
    $this->settings->forget('theme.active');

    expect($this->settings->has('theme.active'))->toBeFalse()
        ->and($this->settings->get('theme.active'))->toBe('default');
});

it('can use a cache time to live', function () {
    $settings = new Settings(Cache::store(), 'verso.settings.short', 60);
    $settings->set('site.name', 'Taller de Cerámica');

    expect($settings->get('site.name'))->toBe('Taller de Cerámica')
        ->and(Cache::has('verso.settings.short'))->toBeTrue();
});
