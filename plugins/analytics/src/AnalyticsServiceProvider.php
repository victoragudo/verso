<?php

namespace Verso\Plugins\Analytics;

use App\Models\User;
use App\Verso\Core\Admin\Dashboard;
use App\Verso\Core\Admin\DashboardStat;
use App\Verso\Core\Enums\Capability;
use App\Verso\Plugins\Filters\ThemeFooterFilter;
use App\Verso\Plugins\PluginServiceProvider;
use App\Verso\System\Config\ConfigParameter;
use App\Verso\System\Config\Unit;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Verso\Plugins\Analytics\Actions\RecordPageView;
use Verso\Plugins\Analytics\Actions\RollupDay;
use Verso\Plugins\Analytics\Console\RollupAnalyticsCommand;
use Verso\Plugins\Analytics\Jobs\RollupAnalytics;
use Verso\Plugins\Analytics\Reports\AnalyticsReport;
use Verso\Plugins\Analytics\Reports\DayAggregator;
use Verso\Plugins\Analytics\Support\AnalyticsClock;
use Verso\Plugins\Analytics\Support\AnalyticsSettings;
use Verso\Plugins\Analytics\Support\Beacon;
use Verso\Plugins\Analytics\Support\BotDetector;
use Verso\Plugins\Analytics\Support\PathExclusions;
use Verso\Plugins\Analytics\Support\VisitorHasher;

// Boots the Analytics plugin: the beacon, the collector, the daily rollup and the admin page.
class AnalyticsServiceProvider extends PluginServiceProvider
{
    // Name of the rate limiter used by the beacon endpoint.
    public const RATE_LIMITER = 'verso-analytics';

    public function register(): void
    {
        $this->mergeConfigFrom($this->path('config/analytics.php'), 'analytics');
        $this->registerSystemSettings('Analytics', 'analytics', ...$this->systemSettings());

        $this->app->singleton(VisitorHasher::class);
        $this->app->singleton(BotDetector::class, fn (): BotDetector => new BotDetector(
            array_values(array_map(strval(...), (array) config('analytics.bots'))),
        ));
        $this->app->bind(RecordPageView::class, fn ($app): RecordPageView => new RecordPageView(
            bots: $app->make(BotDetector::class),
            hasher: $app->make(VisitorHasher::class),
            clock: $app->make(AnalyticsClock::class),
            exclusions: $app->make(PathExclusions::class),
            maxPathLength: max(1, (int) config('analytics.max_path_length')),
        ));
        $this->app->bind(PathExclusions::class, fn (): PathExclusions => new PathExclusions(
            patterns: [
                ...array_values(array_map(strval(...), (array) config('analytics.exclude_paths'))),
                (string) config('verso.admin.prefix'),
                (string) config('analytics.endpoint'),
            ],
            sensitiveKeys: array_values(array_map(strval(...), (array) config('analytics.sensitive_query_keys'))),
            tokenMinLength: max(8, (int) config('analytics.token_min_length')),
        ));
        $this->app->bind(RollupDay::class, fn ($app): RollupDay => new RollupDay(
            aggregator: $app->make(DayAggregator::class),
            chunk: (int) config('analytics.rollup_chunk'),
        ));
        $this->app->bind(AnalyticsReport::class, fn ($app): AnalyticsReport => new AnalyticsReport(
            clock: $app->make(AnalyticsClock::class),
            aggregator: $app->make(DayAggregator::class),
            topLimit: (int) config('analytics.top_limit'),
        ));
        $this->app->bind(Beacon::class, fn ($app): Beacon => new Beacon(
            endpoint: route('analytics.collect', [], false),
            sensitiveKeys: $app->make(PathExclusions::class)->sensitiveKeys(),
            tabletFrom: (int) config('analytics.breakpoints.tablet'),
            desktopFrom: (int) config('analytics.breakpoints.desktop'),
        ));
    }

    public function boot(): void
    {
        $this->loadPluginMigrations();
        $this->loadRoutesFrom($this->path('routes/public.php'));
        $this->loadAdminRoutes('routes/admin.php');

        RateLimiter::for(self::RATE_LIMITER, fn (Request $request): Limit => Limit::perMinute(max(1, (int) config('analytics.rate_limit')))
            ->by((string) $request->ip()));

        $this->addFilter(ThemeFooterFilter::class, fn (string $html, Request $request): string => $this->shouldTrack($request)
            ? $html.$this->app->make(Beacon::class)->script()
            : $html);

        $this->app->booted(fn () => $this->app->make(Dashboard::class)->addStat(new DashboardStat(
            key: 'analytics-visitors-today',
            label: 'Visitors today',
            count: fn (): int => $this->app->make(AnalyticsReport::class)->visitorsToday(),
            order: 5,
            capability: $this->capability(),
            route: 'admin.analytics.index',
            icon: 'analytics',
            plugin: 'analytics',
        )));

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->job(new RollupAnalytics)
                ->dailyAt((string) config('analytics.rollup_at'))
                ->timezone($this->app->make(AnalyticsClock::class)->now()->getTimezone())
                ->name('analytics:rollup')
                ->withoutOverlapping();
        });

        if ($this->app->runningInConsole()) {
            $this->commands([RollupAnalyticsCommand::class]);
        }
    }

    // The beacon is left out when tracking is off, on private pages, and for logged-in users of the ignored roles.
    public function shouldTrack(Request $request): bool
    {
        if (! (bool) config('analytics.enabled')) {
            return false;
        }

        if ($this->app->make(PathExclusions::class)->excludes('/'.ltrim($request->path(), '/'), (string) $request->getQueryString())) {
            return false;
        }

        $user = $request->user();

        return ! $this->app->make(AnalyticsSettings::class)->ignores($user instanceof User ? $user : null);
    }

    // A core capability or a declared one, like the default "analytics.view".
    protected function capability(): Capability|string
    {
        $capability = (string) config('analytics.capability');

        return Capability::tryFrom($capability) ?? ($capability !== '' ? $capability : Capability::ManageSettings);
    }

    // Limits of this plugin that admins can change in System settings.
    /** @return list<ConfigParameter> */
    protected function systemSettings(): array
    {
        $group = $this->systemGroup();

        return [
            new ConfigParameter('analytics.rate_limit', $group, 'Page views per minute', 'Page views counted each minute from one IP address.', unit: Unit::Requests, min: 1, max: 10_000, env: 'ANALYTICS_RATE_LIMIT'),
            new ConfigParameter('analytics.max_path_length', $group, 'Longest page path', 'Longest page path that is recorded.', unit: Unit::Characters, min: 50, max: 2000, env: 'ANALYTICS_MAX_PATH_LENGTH'),
            new ConfigParameter('analytics.top_limit', $group, 'Rows in top lists', 'Rows in the top pages and top referrers lists.', unit: Unit::Items, min: 3, max: 100, env: 'ANALYTICS_TOP_LIMIT'),
            new ConfigParameter('analytics.rollup_chunk', $group, 'Rows per roll up insert', 'Rows written at once when a day is rolled up.', unit: Unit::Items, min: 50, max: 10_000, env: 'ANALYTICS_ROLLUP_CHUNK'),
        ];
    }
}
