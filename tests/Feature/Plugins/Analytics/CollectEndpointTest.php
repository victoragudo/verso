<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\AnalyticsPlugin;
use Verso\Plugins\Analytics\AnalyticsServiceProvider;
use Verso\Plugins\Analytics\Models\AnalyticsEvent;

beforeEach(function () {
    AnalyticsPlugin::boot();
    $this->travelTo(CarbonImmutable::parse('2026-11-17 19:45:00', 'UTC'));
    RateLimiter::clear(AnalyticsServiceProvider::RATE_LIMITER);
});

function sendBeacon(array $fields = [], array $headers = [])
{
    return test()->withHeaders(['User-Agent' => AnalyticsPlugin::FIREFOX, ...$headers])
        ->post('/_verso/collect', ['p' => '/blog/pasteis-de-nata', 'r' => 'duckduckgo.com', 'w' => 'mobile', ...$fields]);
}

it('records a beacon and answers with no content', function () {
    sendBeacon()->assertNoContent();

    $event = AnalyticsEvent::query()->sole();

    expect($event->path)->toBe('/blog/pasteis-de-nata')
        ->and($event->referrer_host)->toBe('duckduckgo.com')
        ->and($event->device)->toBe('mobile');
});

it('sets no cookies', function () {
    $response = sendBeacon();

    expect($response->headers->getCookies())->toBe([]);
});

it('works without a csrf token because it has no session', function () {
    $this->withMiddleware();

    sendBeacon()->assertNoContent();

    expect(AnalyticsEvent::query()->count())->toBe(1);
});

it('respects do not track', function () {
    sendBeacon([], ['DNT' => '1'])->assertNoContent();

    expect(AnalyticsEvent::query()->count())->toBe(0);
});

it('respects global privacy control', function () {
    sendBeacon([], ['Sec-GPC' => '1'])->assertNoContent();

    expect(AnalyticsEvent::query()->count())->toBe(0);
});

it('counts visitors who send DNT 0', function () {
    sendBeacon([], ['DNT' => '0'])->assertNoContent();

    expect(AnalyticsEvent::query()->count())->toBe(1);
});

it('ignores bots', function () {
    sendBeacon([], ['User-Agent' => 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)'])->assertNoContent();

    expect(AnalyticsEvent::query()->count())->toBe(0);
});

it('ignores bad data quietly', function (array $fields) {
    sendBeacon($fields)->assertNoContent();

    expect(AnalyticsEvent::query()->count())->toBe(0);
})->with([
    'missing path' => [['p' => '']],
    'full url' => [['p' => 'https://cafe-lisboa.example/menu']],
    'array path' => [['p' => ['/menu']]],
    'exact width' => [['w' => '1440']],
]);

it('does nothing when analytics is turned off', function () {
    config(['analytics.enabled' => false]);

    sendBeacon()->assertNoContent();

    expect(AnalyticsEvent::query()->count())->toBe(0);
});

it('throttles one ip address', function () {
    config(['analytics.rate_limit' => 3]);

    sendBeacon()->assertNoContent();
    sendBeacon()->assertNoContent();
    sendBeacon()->assertNoContent();
    sendBeacon()->assertTooManyRequests();

    expect(AnalyticsEvent::query()->count())->toBe(3);
});

it('does not record get requests', function () {
    $this->get('/_verso/collect?p=/menu&w=mobile')->assertNotFound();

    expect(AnalyticsEvent::query()->count())->toBe(0);
});
