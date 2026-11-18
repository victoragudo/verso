<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Plugins\Filters;
use App\Verso\Plugins\Filters\PrivatePathsFilter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\AnalyticsPlugin;
use Tests\Support\ShopFixtures;
use Verso\Plugins\Analytics\Actions\RecordPageView;
use Verso\Plugins\Analytics\AnalyticsServiceProvider;
use Verso\Plugins\Analytics\Models\AnalyticsEvent;
use Verso\Plugins\Analytics\Support\Beacon;
use Verso\Plugins\Analytics\Support\PathExclusions;

beforeEach(function () {
    AnalyticsPlugin::boot();
    $this->travelTo(CarbonImmutable::parse('2026-11-17 10:30:00', 'UTC'));
    RateLimiter::clear(AnalyticsServiceProvider::RATE_LIMITER);
});

function beaconFor(string $path)
{
    return test()->withHeaders(['User-Agent' => AnalyticsPlugin::FIREFOX])
        ->post('/_verso/collect', ['p' => $path, 'r' => '', 'w' => 'desktop']);
}

it('never records private core paths', function (string $path) {
    expect(app(RecordPageView::class)->cleanPath($path))->toBeNull();
})->with([
    '/admin/entries/post',
    '/api/v1/entries/post',
    '/install',
    '/preview',
    '/blog/preview',
    '/account/orders',
    '/settings/profile',
    '/login',
    '/register',
    '/reset-password/0b1c2d3e4f',
    '/two-factor-challenge',
    '/invitation/14/a9F2kQ7xLm3Rt8Vw1Zp6',
    '/_verso/collect',
]);

it('keeps public paths that only look similar', function (string $path) {
    expect(app(RecordPageView::class)->cleanPath($path))->toBe($path);
})->with([
    '/administration-tips',
    '/blog/preview-of-the-autumn-menu',
    '/installing-a-wood-stove',
    '/accountants-in-porto',
    '/blog/a-weekend-in-the-douro-valley-and-the-best-port-cellars',
    '/blog/top-10-pastries-2026',
]);

it('drops paths that hold a token', function (string $path) {
    expect(app(RecordPageView::class)->cleanPath($path))->toBeNull();
})->with([
    '/checkout/orders/5b0f6c1e-3a2d-4f8e-9c71-2d4b6a8e0f13',
    '/downloads/9f8e7d6c5b4a39281706f5e4d3c2b1a0',
    '/gift/Xk82LmQp4Rz9Tw1Vb7Nc',
]);

it('drops paths sent with sensitive query keys', function (string $path) {
    expect(app(RecordPageView::class)->cleanPath($path))->toBeNull();
})->with([
    '/welcome?token=Hq72pLm4',
    '/newsletter-thanks?Signature=abc123&expires=1795000000',
    '/members/area?email=ines%40cafe-lisboa.example',
]);

it('keeps paths with harmless query keys', function () {
    expect(app(RecordPageView::class)->cleanPath('/blog/pasteis-de-nata?utm_source=newsletter&page=2'))
        ->toBe('/blog/pasteis-de-nata');
});

it('reads extra exclude patterns from config', function () {
    config(['analytics.exclude_paths' => ['/members-lounge', '/events/*/tickets']]);

    $record = app(RecordPageView::class);

    expect($record->cleanPath('/members-lounge/library'))->toBeNull()
        ->and($record->cleanPath('/events/lisbon-wine-fair/tickets'))->toBeNull()
        ->and($record->cleanPath('/events/lisbon-wine-fair'))->toBe('/events/lisbon-wine-fair')
        ->and($record->cleanPath('/admin'))->toBeNull();
});

it('lets plugins add private paths through the filter', function () {
    Filters::add(PrivatePathsFilter::class, fn (array $patterns): array => [...$patterns, '/bookings/*/receipt']);

    beaconFor('/bookings/table-for-four/receipt')->assertNoContent();
    beaconFor('/bookings')->assertNoContent();

    expect(AnalyticsEvent::query()->pluck('path')->all())->toBe(['/bookings']);
});

it('ignores empty patterns from plugins', function () {
    Filters::add(PrivatePathsFilter::class, fn (array $patterns): array => [...$patterns, '', '   ']);

    expect(PrivatePathsFilter::apply(['/admin']))->toBe(['/admin'])
        ->and(app(RecordPageView::class)->cleanPath('/about'))->toBe('/about');
});

it('leaves shop checkout, cart and order pages out', function () {
    ShopFixtures::boot();

    $record = app(RecordPageView::class);

    expect($record->cleanPath('/checkout'))->toBeNull()
        ->and($record->cleanPath('/checkout/orders/5b0f6c1e-3a2d-4f8e-9c71-2d4b6a8e0f13'))->toBeNull()
        ->and($record->cleanPath('/cart'))->toBeNull()
        ->and($record->cleanPath('/shop/webhooks/stripe'))->toBeNull()
        ->and($record->cleanPath('/shop/ceramic-espresso-cup'))->toBe('/shop/ceramic-espresso-cup');
});

it('follows custom shop paths', function () {
    config(['shop.routes.checkout' => 'kassa']);
    ShopFixtures::boot();

    expect(app(RecordPageView::class)->cleanPath('/kassa'))->toBeNull();
});

it('does not print the beacon on the shop cart page', function () {
    ShopFixtures::boot();

    $this->get('/cart')->assertOk()->assertDontSee('n.sendBeacon(', false);
});

it('does not print the beacon on pages opened with a private link', function () {
    config(['verso.page_cache.enabled' => false]);
    $entry = Entry::factory()->published()->by(User::factory()->author()->create())->create([
        'title' => 'Welcome to the Club',
        'slug' => 'welcome-to-the-club',
    ]);

    $this->get($entry->path().'?token=Hq72pLm4')->assertOk()->assertDontSee('n.sendBeacon(', false);
    $this->get($entry->path().'?utm_source=newsletter')->assertOk()->assertSee('n.sendBeacon(', false);
});

it('skips the beacon in the browser when the url has a sensitive key', function () {
    expect(app(Beacon::class)->script())
        ->toContain('new URLSearchParams(l.search.toLowerCase())')
        ->toContain('"token"')
        ->toContain('"signature"');
});

it('keeps the beacon under one kilobyte with the default keys', function () {
    expect(strlen(app(Beacon::class)->script()))->toBeLessThan(1024);
});

it('matches patterns by whole path parts', function () {
    $exclusions = new PathExclusions(['/user'], [], 20);

    expect($exclusions->excludes('/user'))->toBeTrue()
        ->and($exclusions->excludes('/user/confirm-password'))->toBeTrue()
        ->and($exclusions->excludes('/user-guide'))->toBeFalse()
        ->and($exclusions->excludes('/USER/profile'))->toBeTrue();
});

it('treats only mixed letters and digits as tokens', function () {
    $exclusions = new PathExclusions([], [], 20);

    expect($exclusions->hasToken('/blog/the-best-pasteis-de-nata-in-belem-2026'))->toBeFalse()
        ->and($exclusions->hasToken('/blog/supercalifragilisticexpialidocious'))->toBeFalse()
        ->and($exclusions->hasToken('/archive/20261117081500123456'))->toBeFalse()
        ->and($exclusions->hasToken('/r/a1b2c3d4e5f6a7b8c9d0'))->toBeTrue();
});
