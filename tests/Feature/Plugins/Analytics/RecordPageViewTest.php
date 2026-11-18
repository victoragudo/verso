<?php

use Carbon\CarbonImmutable;
use Tests\Support\AnalyticsPlugin;
use Verso\Plugins\Analytics\Actions\RecordPageView;
use Verso\Plugins\Analytics\Models\AnalyticsEvent;
use Verso\Plugins\Analytics\Support\PageView;

beforeEach(function () {
    AnalyticsPlugin::boot();
    $this->travelTo(CarbonImmutable::parse('2026-11-17 08:15:00', 'UTC'));
    $this->record = app(RecordPageView::class);
});

function douroView(array $overrides = []): PageView
{
    return new PageView(...[
        'path' => '/blog/a-weekend-in-the-douro-valley',
        'referrerHost' => 'news.ycombinator.com',
        'device' => 'desktop',
        'ip' => '198.51.100.7',
        'userAgent' => AnalyticsPlugin::FIREFOX,
        'site' => 'cafe-lisboa.example',
        ...$overrides,
    ]);
}

it('saves an anonymous page view', function () {
    expect($this->record->handle(douroView()))->toBeTrue();

    $event = AnalyticsEvent::query()->sole();

    expect(substr((string) $event->day, 0, 10))->toBe('2026-11-17')
        ->and($event->path)->toBe('/blog/a-weekend-in-the-douro-valley')
        ->and($event->referrer_host)->toBe('news.ycombinator.com')
        ->and($event->device)->toBe('desktop')
        ->and($event->visitor)->toHaveLength(64);
});

it('never stores the ip address or the user agent', function () {
    $this->record->handle(douroView());

    $stored = json_encode(AnalyticsEvent::query()->sole()->getAttributes());

    expect($stored)->not->toContain('198.51.100.7')
        ->and($stored)->not->toContain('Firefox');
});

it('skips visitors who opted out', function () {
    expect($this->record->handle(douroView(['optedOut' => true])))->toBeFalse()
        ->and(AnalyticsEvent::query()->count())->toBe(0);
});

it('skips bots', function () {
    expect($this->record->handle(douroView(['userAgent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])))->toBeFalse()
        ->and(AnalyticsEvent::query()->count())->toBe(0);
});

it('skips unknown device buckets', function (?string $device) {
    expect($this->record->handle(douroView(['device' => $device])))->toBeFalse();
})->with(['1920', 'smart-fridge', null]);

it('cleans paths', function (string $path, ?string $clean) {
    expect($this->record->cleanPath($path))->toBe($clean);
})->with([
    ['/', '/'],
    ['/menu/', '/menu'],
    ['/blog/pasteis-de-nata?utm_source=newsletter', '/blog/pasteis-de-nata'],
    ['/about#team', '/about'],
    ['blog/no-leading-slash', null],
    ['//evil.example/path', null],
    ['https://evil.example/', null],
    ["/line\nbreak", null],
    ['/admin', null],
    ['/admin/entries/post', null],
    ['/_verso/collect', null],
    ['/administration-tips', '/administration-tips'],
    ['', null],
]);

it('rejects paths longer than the limit', function () {
    expect($this->record->cleanPath('/'.str_repeat('a', 300)))->toBeNull()
        ->and($this->record->cleanPath('/'.str_repeat('a', 254)))->toHaveLength(255);
});

it('keeps only hosts of other sites as referrers', function (?string $host, ?string $clean) {
    expect($this->record->cleanReferrer($host, 'cafe-lisboa.example'))->toBe($clean);
})->with([
    ['news.ycombinator.com', 'news.ycombinator.com'],
    ['WWW.Google.COM', 'www.google.com'],
    ['localhost:5173', 'localhost:5173'],
    ['cafe-lisboa.example', null],
    ['cafe-lisboa.example:8443', null],
    ['', null],
    [null, null],
    ['https://google.com/search', null],
    ['<script>', null],
    ['bad host.example', null],
]);

it('stores a direct visit with no referrer', function () {
    $this->record->handle(douroView(['referrerHost' => null]));

    expect(AnalyticsEvent::query()->sole()->referrer_host)->toBeNull();
});

it('counts the same reader twice as one visitor hash', function () {
    $this->record->handle(douroView());
    $this->record->handle(douroView(['path' => '/menu']));
    $this->record->handle(douroView(['ip' => '198.51.100.99']));

    expect(AnalyticsEvent::query()->distinct()->count('visitor'))->toBe(2);
});
