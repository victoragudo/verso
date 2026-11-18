<?php

use App\Verso\Core\Settings\Settings;
use App\Verso\Site\Actions\UpdateSiteSettings;
use App\Verso\Site\Events\SiteSettingsUpdated;
use Illuminate\Support\Facades\Event;

it('saves only the known keys', function () {
    Event::fake([SiteSettingsUpdated::class]);

    $saved = app(UpdateSiteSettings::class)->handle([
        'site.name' => 'Galician Hiking Notes',
        'site.tagline' => 'Trails, weather and good bread',
        'plugins.enabled' => ['spam-guard'],
    ]);

    expect($saved)->toBe(['site.name' => 'Galician Hiking Notes', 'site.tagline' => 'Trails, weather and good bread'])
        ->and(app(Settings::class)->get('site.name'))->toBe('Galician Hiking Notes')
        ->and(app(Settings::class)->has('plugins.enabled'))->toBeFalse();

    Event::assertDispatched(SiteSettingsUpdated::class, fn (SiteSettingsUpdated $event) => $event->keys === ['site.name', 'site.tagline']);
});

it('returns the current value of every key with defaults', function () {
    app(Settings::class)->set('reading.posts_per_page', 8);

    $current = app(UpdateSiteSettings::class)->current();

    expect(array_keys($current))->toBe(UpdateSiteSettings::KEYS)
        ->and($current['reading.posts_per_page'])->toBe(8)
        ->and($current)->not->toHaveKey('seo.robots_txt');
});
