<?php

use App\Models\User;
use App\Verso\Core\Settings\Settings;
use Carbon\CarbonImmutable;
use Tests\Support\AnalyticsPlugin;
use Verso\Plugins\Analytics\Enums\Device;
use Verso\Plugins\Analytics\Models\VisitorSalt;
use Verso\Plugins\Analytics\Support\AnalyticsClock;
use Verso\Plugins\Analytics\Support\AnalyticsSettings;
use Verso\Plugins\Analytics\Support\BotDetector;
use Verso\Plugins\Analytics\Support\VisitorHasher;

beforeEach(function () {
    AnalyticsPlugin::boot();
    $this->travelTo(CarbonImmutable::parse('2026-11-17 10:30:00', 'UTC'));
});

it('puts screen widths in device buckets', function (int $width, string $device) {
    expect(Device::forWidth($width, 768, 1024)->value)->toBe($device);
})->with([
    [320, 'mobile'],
    [767, 'mobile'],
    [768, 'tablet'],
    [1023, 'tablet'],
    [1024, 'desktop'],
    [2560, 'desktop'],
]);

it('labels devices for people', function () {
    expect(Device::Tablet->label())->toBe('Tablet');
});

it('spots crawlers and scripts by user agent', function (string $userAgent) {
    expect(app(BotDetector::class)->isBot($userAgent))->toBeTrue();
})->with([
    'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
    'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
    'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)',
    'curl/8.7.1',
    'python-requests/2.32.3',
    'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/130.0.0.0 Safari/537.36',
    'Mozilla/5.0 (Linux; Android 11; moto g power (2022)) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36 Chrome-Lighthouse',
    '',
]);

it('lets real browsers through', function (string $userAgent) {
    expect(app(BotDetector::class)->isBot($userAgent))->toBeFalse();
})->with([
    AnalyticsPlugin::FIREFOX,
    AnalyticsPlugin::IPHONE,
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36 Edg/131.0.0.0',
]);

it('reads the bot words from config', function () {
    $detector = new BotDetector(['FeedReader']);

    expect($detector->isBot('NetNewsWire FeedReader/6.1'))->toBeTrue()
        ->and($detector->isBot('Googlebot/2.1'))->toBeFalse();
});

it('gives the same visitor the same hash on one day', function () {
    $hasher = app(VisitorHasher::class);

    $first = $hasher->hash('2026-11-17', '203.0.113.24', AnalyticsPlugin::FIREFOX, 'cafe-lisboa.example');
    $second = $hasher->hash('2026-11-17', '203.0.113.24', AnalyticsPlugin::FIREFOX, 'cafe-lisboa.example');

    expect($first)->toBe($second)->toHaveLength(64)
        ->and($first)->not->toContain('203.0.113.24');
});

it('gives different hashes for another ip, browser or site', function () {
    $hasher = app(VisitorHasher::class);
    $base = $hasher->hash('2026-11-17', '203.0.113.24', AnalyticsPlugin::FIREFOX, 'cafe-lisboa.example');

    expect($hasher->hash('2026-11-17', '203.0.113.25', AnalyticsPlugin::FIREFOX, 'cafe-lisboa.example'))->not->toBe($base)
        ->and($hasher->hash('2026-11-17', '203.0.113.24', AnalyticsPlugin::IPHONE, 'cafe-lisboa.example'))->not->toBe($base)
        ->and($hasher->hash('2026-11-17', '203.0.113.24', AnalyticsPlugin::FIREFOX, 'porto-bakery.example'))->not->toBe($base);
});

it('cannot link a visitor across days because the salt changes', function () {
    $hasher = app(VisitorHasher::class);

    expect($hasher->hash('2026-11-17', '203.0.113.24', AnalyticsPlugin::FIREFOX, 'cafe-lisboa.example'))
        ->not->toBe($hasher->hash('2026-11-18', '203.0.113.24', AnalyticsPlugin::FIREFOX, 'cafe-lisboa.example'));
});

it('keeps one salt per day and deletes older salts when a new day starts', function () {
    $hasher = app(VisitorHasher::class);

    $monday = $hasher->saltFor('2026-11-16');
    expect($hasher->saltFor('2026-11-16'))->toBe($monday)->toHaveLength(64);

    $hasher->saltFor('2026-11-17');

    expect(VisitorSalt::query()->pluck('day')->map(fn ($day) => substr((string) $day, 0, 10))->all())->toBe(['2026-11-17']);
});

it('reuses the stored salt in a new hasher instance', function () {
    $salt = app(VisitorHasher::class)->saltFor('2026-11-17');

    expect((new VisitorHasher)->saltFor('2026-11-17'))->toBe($salt);
});

it('gives today and the last days in the site time zone', function () {
    app(Settings::class)->set('site.timezone', 'Pacific/Auckland');
    $clock = app(AnalyticsClock::class);

    expect($clock->today())->toBe('2026-11-17')
        ->and($clock->lastDays(3))->toBe(['2026-11-15', '2026-11-16', '2026-11-17'])
        ->and($clock->daysAgo(30))->toBe('2026-10-18');

    $this->travelTo(CarbonImmutable::parse('2026-11-17 12:30:00', 'UTC'));

    expect($clock->today())->toBe('2026-11-18');
});

it('returns at least today for the last days', function () {
    expect(app(AnalyticsClock::class)->lastDays(0))->toBe(['2026-11-17']);
});

it('reads settings with config defaults and clamps the retention', function () {
    $settings = app(AnalyticsSettings::class);

    expect($settings->toArray())->toBe(['retention_days' => 30, 'ignore_roles' => ['admin']]);

    $settings->update(14, ['editor']);
    expect($settings->retentionDays())->toBe(14)->and($settings->ignoredRoles())->toBe(['editor']);

    app(Settings::class)->set(AnalyticsSettings::RETENTION_DAYS, 9000);
    expect($settings->retentionDays())->toBe(365);

    app(Settings::class)->set(AnalyticsSettings::RETENTION_DAYS, -4);
    expect($settings->retentionDays())->toBe(1);
});

it('reads the ignored roles from config until they are saved', function () {
    config(['analytics.defaults.ignore_roles' => ['admin', 'editor', 'pirate']]);

    expect(app(AnalyticsSettings::class)->ignoredRoles())->toBe(['admin', 'editor']);
});

it('keeps the old admins switch working until roles are saved', function (bool $legacy, array $roles) {
    app(Settings::class)->set(AnalyticsSettings::LEGACY_IGNORE_ADMINS, $legacy);

    expect(app(AnalyticsSettings::class)->ignoredRoles())->toBe($roles);
})->with([
    'switch on' => [true, ['admin']],
    'switch off' => [false, []],
]);

it('drops unknown and repeated roles when saving', function () {
    $settings = app(AnalyticsSettings::class);
    $settings->update(30, ['author', 'sommelier', 'author', 'shop_manager']);

    expect($settings->ignoredRoles())->toBe(['author', 'shop_manager']);
});

it('tells which signed-in users are ignored', function () {
    $settings = app(AnalyticsSettings::class);
    $settings->update(30, ['admin', 'editor']);

    expect($settings->ignores(User::factory()->editor()->make()))->toBeTrue()
        ->and($settings->ignores(User::factory()->author()->make()))->toBeFalse()
        ->and($settings->ignores(null))->toBeFalse();
});
