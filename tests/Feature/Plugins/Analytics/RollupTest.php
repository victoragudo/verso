<?php

use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Tests\Support\AnalyticsPlugin;
use Verso\Plugins\Analytics\Actions\PruneAnalytics;
use Verso\Plugins\Analytics\Actions\RollupDay;
use Verso\Plugins\Analytics\Actions\RollupPendingDays;
use Verso\Plugins\Analytics\Jobs\RollupAnalytics;
use Verso\Plugins\Analytics\Models\AnalyticsEvent;
use Verso\Plugins\Analytics\Models\DailyStat;
use Verso\Plugins\Analytics\Models\VisitorSalt;
use Verso\Plugins\Analytics\Reports\DayAggregator;
use Verso\Plugins\Analytics\Support\AnalyticsSettings;

beforeEach(function () {
    AnalyticsPlugin::boot();
    $this->travelTo(CarbonImmutable::parse('2026-11-17 09:00:00', 'UTC'));
});

// Three readers on Monday: two on the nata post (one from Google), one on the menu from a phone.
function mondayTraffic(): void
{
    $ana = hash('sha256', 'ana');
    $rui = hash('sha256', 'rui');
    $ines = hash('sha256', 'ines');

    AnalyticsPlugin::event(['day' => '2026-11-16', 'path' => '/blog/pasteis-de-nata', 'visitor' => $ana, 'referrer_host' => 'www.google.com']);
    AnalyticsPlugin::event(['day' => '2026-11-16', 'path' => '/blog/pasteis-de-nata', 'visitor' => $rui]);
    AnalyticsPlugin::event(['day' => '2026-11-16', 'path' => '/menu', 'visitor' => $ana]);
    AnalyticsPlugin::event(['day' => '2026-11-16', 'path' => '/menu', 'visitor' => $ines, 'device' => 'mobile', 'referrer_host' => 'www.google.com']);
}

function statFor(string $dimension, string $value = ''): ?DailyStat
{
    return DailyStat::query()->where('day', '2026-11-16')->where('dimension', $dimension)->where('value', $value)->first();
}

it('aggregates one day into totals, pages, referrers and devices', function () {
    mondayTraffic();

    $rows = collect(app(DayAggregator::class)->rows('2026-11-16'));

    expect($rows->firstWhere('dimension', 'total'))->toMatchArray(['views' => 4, 'visitors' => 3])
        ->and($rows->where('dimension', 'page')->firstWhere('value', '/menu'))->toMatchArray(['views' => 2, 'visitors' => 2])
        ->and($rows->where('dimension', 'page')->firstWhere('value', '/blog/pasteis-de-nata'))->toMatchArray(['views' => 2, 'visitors' => 2])
        ->and($rows->where('dimension', 'referrer')->all())->toHaveCount(1)
        ->and($rows->where('dimension', 'referrer')->first())->toMatchArray(['value' => 'www.google.com', 'views' => 2, 'visitors' => 2])
        ->and($rows->where('dimension', 'device')->firstWhere('value', 'desktop'))->toMatchArray(['views' => 3, 'visitors' => 2]);
});

it('returns no rows for a quiet day', function () {
    expect(app(DayAggregator::class)->rows('2026-11-10'))->toBe([]);
});

it('counts unique visitors of a day', function () {
    mondayTraffic();

    expect(app(DayAggregator::class)->visitors('2026-11-16'))->toBe(3)
        ->and(app(DayAggregator::class)->visitors('2026-11-17'))->toBe(0);
});

it('writes the daily rows of a day', function () {
    mondayTraffic();

    expect(app(RollupDay::class)->handle('2026-11-16'))->toBe(6);

    expect(statFor('total')->views)->toBe(4)
        ->and(statFor('total')->visitors)->toBe(3)
        ->and(statFor('page', '/menu')->views)->toBe(2)
        ->and(statFor('device', 'mobile')->visitors)->toBe(1);
});

it('gives the same result when a day is rolled up twice', function () {
    mondayTraffic();

    app(RollupDay::class)->handle('2026-11-16');
    app(RollupDay::class)->handle('2026-11-16');

    expect(DailyStat::query()->count())->toBe(6)
        ->and(statFor('total')->views)->toBe(4);
});

it('writes rows in chunks from config', function () {
    config(['analytics.rollup_chunk' => 2]);
    mondayTraffic();

    expect(app(RollupDay::class)->handle('2026-11-16'))->toBe(6)
        ->and(DailyStat::query()->count())->toBe(6);
});

it('finds past days that are not rolled up yet and never today', function () {
    mondayTraffic();
    AnalyticsPlugin::event(['day' => '2026-11-14']);
    AnalyticsPlugin::event(['day' => '2026-11-17']);

    $pending = app(RollupPendingDays::class);

    expect($pending->pendingDays())->toBe(['2026-11-14', '2026-11-16'])
        ->and($pending->handle())->toBe(['2026-11-14', '2026-11-16'])
        ->and($pending->pendingDays())->toBe([])
        ->and(DailyStat::query()->where('day', '2026-11-17')->count())->toBe(0);
});

it('deletes raw events older than the retention days', function () {
    app(AnalyticsSettings::class)->update(7, ['admin']);
    AnalyticsPlugin::event(['day' => '2026-11-10']);
    AnalyticsPlugin::event(['day' => '2026-11-11']);
    AnalyticsPlugin::event(['day' => '2026-11-17']);

    $pruned = app(PruneAnalytics::class)->handle();

    expect($pruned['events'])->toBe(1)
        ->and(AnalyticsEvent::query()->pluck('day')->map(fn ($day) => substr((string) $day, 0, 10))->sort()->values()->all())
        ->toBe(['2026-11-11', '2026-11-17']);
});

it('deletes the salts of past days', function () {
    VisitorSalt::query()->insert([
        ['day' => '2026-11-15', 'salt' => str_repeat('a', 64)],
        ['day' => '2026-11-16', 'salt' => str_repeat('b', 64)],
        ['day' => '2026-11-17', 'salt' => str_repeat('c', 64)],
    ]);

    expect(app(PruneAnalytics::class)->handle()['salts'])->toBe(2)
        ->and(VisitorSalt::query()->count())->toBe(1);
});

it('rolls up before it prunes, so old days keep their totals', function () {
    app(AnalyticsSettings::class)->update(1, ['admin']);
    mondayTraffic();

    (new RollupAnalytics)->handle(app(RollupPendingDays::class), app(PruneAnalytics::class));

    expect(AnalyticsEvent::query()->count())->toBe(0)
        ->and(statFor('total')->views)->toBe(4);
});

it('runs the rollup from the console', function () {
    mondayTraffic();

    $this->artisan('analytics:rollup')
        ->expectsOutput('Rolled up 1 day(s), from 2026-11-16 to 2026-11-16.')
        ->assertSuccessful();

    expect(statFor('total')->visitors)->toBe(3);
});

it('says when there is nothing to roll up', function () {
    $this->artisan('analytics:rollup', ['--no-prune' => true])
        ->expectsOutput('No days to roll up.')
        ->doesntExpectOutputToContain('Deleted')
        ->assertSuccessful();
});

it('rolls up one given day again from the console', function () {
    mondayTraffic();
    app(RollupDay::class)->handle('2026-11-16');
    AnalyticsPlugin::event(['day' => '2026-11-16', 'path' => '/contact', 'visitor' => hash('sha256', 'marta')]);

    $this->artisan('analytics:rollup', ['--day' => '2026-11-16', '--no-prune' => true])->assertSuccessful();

    expect(statFor('total')->views)->toBe(5)
        ->and(statFor('page', '/contact')->views)->toBe(1);
});

it('refuses a badly written day', function () {
    $this->artisan('analytics:rollup', ['--day' => '16/11/2026'])
        ->expectsOutput('The day must look like 2026-11-16.')
        ->assertFailed();
});

it('schedules the rollup every day at the configured time', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => $event->description === 'analytics:rollup');

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('15 0 * * *');
});
