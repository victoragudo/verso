<?php

use App\Verso\Core\Settings\Settings;
use App\Verso\Core\Support\SiteTime;
use Carbon\CarbonImmutable;

// SiteTime for a site in the given time zone.
function siteTimeIn(string $zone): SiteTime
{
    app(Settings::class)->set('site.timezone', $zone);

    return app(SiteTime::class);
}

it('reads the time zone from the settings', function () {
    expect(siteTimeIn('Europe/Madrid')->timezone())->toBe('Europe/Madrid');
});

it('falls back to the app time zone when the setting is not a real zone', function () {
    expect(siteTimeIn('Mars/Olympus_Mons')->timezone())->toBe('UTC');
});

it('shows a UTC date in Madrid winter time', function () {
    $time = siteTimeIn('Europe/Madrid');
    $utc = CarbonImmutable::parse('2026-01-15 08:30:00', 'UTC');

    expect($time->iso($utc))->toBe('2026-01-15T09:30:00+01:00')
        ->and($time->format($utc, 'H:i'))->toBe('09:30');
});

it('shows a UTC date in Madrid summer time', function () {
    $utc = CarbonImmutable::parse('2026-07-15 08:30:00', 'UTC');

    expect(siteTimeIn('Europe/Madrid')->iso($utc))->toBe('2026-07-15T10:30:00+02:00');
});

it('moves a late UTC evening to the next day in Madrid', function () {
    $utc = CarbonImmutable::parse('2026-11-14 23:30:00', 'UTC');

    expect(siteTimeIn('Europe/Madrid')->format($utc, 'F j, Y'))->toBe('November 15, 2026');
});

it('keeps dates as they are on a UTC site', function () {
    expect(siteTimeIn('UTC')->iso(CarbonImmutable::parse('2026-03-01 12:00:00', 'UTC')))->toBe('2026-03-01T12:00:00+00:00');
});

it('returns null for a missing date', function () {
    expect(siteTimeIn('Europe/Madrid')->iso(null))->toBeNull();
});

it('converts a date typed in Madrid to UTC', function (string $typed, string $utc) {
    expect(siteTimeIn('Europe/Madrid')->toUtc($typed)->toDateTimeString())->toBe($utc);
})->with([
    'winter' => ['2026-12-01T09:30', '2026-12-01 08:30:00'],
    'summer' => ['2026-06-01T09:30', '2026-06-01 07:30:00'],
    'with seconds' => ['2026-12-01 23:45:10', '2026-12-01 22:45:10'],
]);

it('converts a date typed in New York to UTC', function () {
    expect(siteTimeIn('America/New_York')->toUtc('2026-12-01T09:30')->toDateTimeString())->toBe('2026-12-01 14:30:00');
});

it('keeps an explicit offset instead of the site zone', function (string $typed) {
    expect(siteTimeIn('Europe/Madrid')->toUtc($typed)->toDateTimeString())->toBe('2026-12-01 08:30:00');
})->with([
    'utc z' => ['2026-12-01T08:30:00Z'],
    'offset' => ['2026-12-01T10:30:00+02:00'],
    'offset without colon' => ['2026-12-01T03:30:00-0500'],
]);

it('goes back to the same wall time both ways', function () {
    $time = siteTimeIn('Europe/Madrid');

    expect($time->format($time->toUtc('2026-11-20T18:05'), 'Y-m-d\TH:i'))->toBe('2026-11-20T18:05');
});

it('turns a Carbon date into UTC', function () {
    $madrid = CarbonImmutable::parse('2026-12-01 09:30:00', 'Europe/Madrid');

    expect(siteTimeIn('UTC')->toUtc($madrid)->toDateTimeString())->toBe('2026-12-01 08:30:00')
        ->and(siteTimeIn('UTC')->toUtc($madrid)->timezoneName)->toBe('UTC');
});
