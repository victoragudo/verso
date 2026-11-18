<?php

use Carbon\CarbonImmutable;
use Tests\Support\AnalyticsPlugin;
use Verso\Plugins\Analytics\Actions\RollupDay;
use Verso\Plugins\Analytics\Models\DailyStat;
use Verso\Plugins\Analytics\Reports\AnalyticsReport;

beforeEach(function () {
    AnalyticsPlugin::boot();
    $this->travelTo(CarbonImmutable::parse('2026-11-17 16:00:00', 'UTC'));
});

function rolledDay(string $day, int $views, int $visitors, array $pages = [], array $referrers = [], array $devices = []): void
{
    $rows = [['day' => $day, 'dimension' => 'total', 'value' => '', 'views' => $views, 'visitors' => $visitors]];

    foreach (['page' => $pages, 'referrer' => $referrers, 'device' => $devices] as $dimension => $values) {
        foreach ($values as $value => [$v, $u]) {
            $rows[] = ['day' => $day, 'dimension' => $dimension, 'value' => $value, 'views' => $v, 'visitors' => $u];
        }
    }

    DailyStat::query()->insert($rows);
}

it('fills every day of the range, oldest first', function () {
    $report = app(AnalyticsReport::class)->build(7);

    expect($report['from'])->toBe('2026-11-11')
        ->and($report['to'])->toBe('2026-11-17')
        ->and(array_column($report['series'], 'date'))->toBe(['2026-11-11', '2026-11-12', '2026-11-13', '2026-11-14', '2026-11-15', '2026-11-16', '2026-11-17'])
        ->and($report['totals'])->toBe(['visitors' => 0, 'views' => 0])
        ->and($report['pages'])->toBe([])
        ->and($report['referrers'])->toBe([]);
});

it('shows every device with a zero share when there is no traffic', function () {
    expect(app(AnalyticsReport::class)->build(7)['devices'])->toBe([
        ['device' => 'mobile', 'label' => 'Mobile', 'views' => 0, 'visitors' => 0, 'share' => 0.0],
        ['device' => 'tablet', 'label' => 'Tablet', 'views' => 0, 'visitors' => 0, 'share' => 0.0],
        ['device' => 'desktop', 'label' => 'Desktop', 'views' => 0, 'visitors' => 0, 'share' => 0.0],
    ]);
});

it('mixes rolled up days with live counts for today', function () {
    rolledDay('2026-11-15', 120, 80, ['/menu' => [70, 50], '/blog/pasteis-de-nata' => [50, 40]], ['www.google.com' => [30, 25]], ['mobile' => [90, 60], 'desktop' => [30, 20]]);
    rolledDay('2026-11-16', 40, 30, ['/menu' => [40, 30]], [], ['desktop' => [40, 30]]);
    AnalyticsPlugin::event(['day' => '2026-11-17', 'path' => '/blog/pasteis-de-nata', 'referrer_host' => 'www.google.com', 'device' => 'tablet']);
    AnalyticsPlugin::event(['day' => '2026-11-17', 'path' => '/blog/pasteis-de-nata', 'visitor' => hash('sha256', 'joana'), 'device' => 'tablet']);

    $report = app(AnalyticsReport::class)->build(7);

    expect($report['totals'])->toBe(['visitors' => 112, 'views' => 162])
        ->and(collect($report['series'])->firstWhere('date', '2026-11-17'))->toBe(['date' => '2026-11-17', 'visitors' => 2, 'views' => 2])
        ->and($report['pages'][0])->toBe(['value' => '/menu', 'views' => 110, 'visitors' => 80])
        ->and($report['pages'][1])->toBe(['value' => '/blog/pasteis-de-nata', 'views' => 52, 'visitors' => 42])
        ->and($report['referrers'])->toBe([['value' => 'www.google.com', 'views' => 31, 'visitors' => 26]])
        ->and(collect($report['devices'])->firstWhere('device', 'tablet'))->toMatchArray(['views' => 2, 'share' => round(2 / 162, 4)])
        ->and(collect($report['devices'])->firstWhere('device', 'mobile')['share'])->toBe(round(90 / 162, 4));
});

it('counts past days live when the rollup has not run yet', function () {
    AnalyticsPlugin::event(['day' => '2026-11-14', 'path' => '/contact']);
    AnalyticsPlugin::event(['day' => '2026-11-14', 'path' => '/contact', 'visitor' => hash('sha256', 'pedro')]);

    $report = app(AnalyticsReport::class)->build(7);

    expect(collect($report['series'])->firstWhere('date', '2026-11-14'))->toBe(['date' => '2026-11-14', 'visitors' => 2, 'views' => 2]);
});

it('does not count a day twice once it is rolled up', function () {
    AnalyticsPlugin::event(['day' => '2026-11-16', 'path' => '/contact']);
    app(RollupDay::class)->handle('2026-11-16');

    expect(app(AnalyticsReport::class)->build(7)['totals'])->toBe(['visitors' => 1, 'views' => 1]);
});

it('leaves out days before the range', function () {
    rolledDay('2026-11-01', 500, 300);
    rolledDay('2026-11-12', 10, 8);

    expect(app(AnalyticsReport::class)->build(7)['totals'])->toBe(['visitors' => 8, 'views' => 10])
        ->and(app(AnalyticsReport::class)->build(30)['totals'])->toBe(['visitors' => 308, 'views' => 510]);
});

it('limits the top lists from config', function () {
    config(['analytics.top_limit' => 2]);
    rolledDay('2026-11-16', 60, 60, ['/menu' => [30, 30], '/contact' => [10, 10], '/about' => [20, 20]]);

    $pages = app(AnalyticsReport::class)->build(7)['pages'];

    expect(array_column($pages, 'value'))->toBe(['/menu', '/about']);
});

it('sorts ties by visitors and then by name', function () {
    rolledDay('2026-11-16', 30, 25, ['/b-side' => [10, 5], '/a-side' => [10, 5], '/c-side' => [10, 9]]);

    expect(array_column(app(AnalyticsReport::class)->build(7)['pages'], 'value'))->toBe(['/c-side', '/a-side', '/b-side']);
});

it('gives the unique visitors of today for the dashboard', function () {
    AnalyticsPlugin::event(['day' => '2026-11-17']);
    AnalyticsPlugin::event(['day' => '2026-11-17', 'path' => '/menu']);
    AnalyticsPlugin::event(['day' => '2026-11-17', 'visitor' => hash('sha256', 'beatriz')]);
    AnalyticsPlugin::event(['day' => '2026-11-16', 'visitor' => hash('sha256', 'yesterday')]);

    expect(app(AnalyticsReport::class)->visitorsToday())->toBe(2);
});
