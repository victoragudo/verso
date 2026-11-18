<?php

namespace App\Verso\Seo;

use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Content\Events\EntryDeleted;
use App\Verso\Content\Events\EntryPublished;
use App\Verso\Content\Events\EntrySaved;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Admin\AdminNav;
use App\Verso\Core\Admin\NavGroup;
use App\Verso\Core\Admin\NavItem;
use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Routing\AdminRoutes;
use App\Verso\Core\Settings\Settings;
use App\Verso\Media\Models\Media;
use App\Verso\Plugins\Slots\AdminSlot;
use App\Verso\Plugins\Slots\AdminSlotRegistry;
use App\Verso\Plugins\Slots\SlotItem;
use App\Verso\Seo\Audit\AuditContext;
use App\Verso\Seo\Audit\Checks\BrokenLinks;
use App\Verso\Seo\Audit\Checks\DuplicateDescriptions;
use App\Verso\Seo\Audit\Checks\DuplicateTitles;
use App\Verso\Seo\Audit\Checks\ImagesWithoutAlt;
use App\Verso\Seo\Audit\Checks\MissingDescription;
use App\Verso\Seo\Audit\Checks\MissingImage;
use App\Verso\Seo\Audit\Checks\NoindexInMenus;
use App\Verso\Seo\Audit\Checks\NoSiteDescription;
use App\Verso\Seo\Audit\Checks\NotFoundPages;
use App\Verso\Seo\Audit\Checks\OrphanPages;
use App\Verso\Seo\Audit\Checks\SearchDiscouraged;
use App\Verso\Seo\Audit\Checks\SitemapOff;
use App\Verso\Seo\Audit\Checks\SlowPages;
use App\Verso\Seo\Audit\Checks\TitleLength;
use App\Verso\Seo\Audit\SeoAudit;
use App\Verso\Seo\Audit\SeoCheck;
use App\Verso\Seo\Audit\SeoCheckRegistry;
use App\Verso\Seo\Console\CheckLinksCommand;
use App\Verso\Seo\Console\PruneNotFoundCommand;
use App\Verso\Seo\Links\CrawlState;
use App\Verso\Seo\Links\LinkExtractor;
use App\Verso\Seo\Links\LinkTargetResolver;
use App\Verso\Seo\NotFound\NotFoundMonitor;
use App\Verso\Seo\NotFound\NotFoundSuggestions;
use App\Verso\Site\Events\SiteSettingsUpdated;
use App\Verso\Site\Models\Menu;
use App\Verso\Site\Models\MenuItem;
use App\Verso\Site\Permalinks\PermalinkResolver;
use App\Verso\Site\Seo\SeoSettings;
use App\Verso\System\Config\ConfigGroup;
use App\Verso\System\Config\ConfigParameter;
use App\Verso\System\Config\ConfigRegistry;
use App\Verso\System\Config\ParameterType;
use App\Verso\System\Config\Unit;
use Illuminate\Cache\RateLimiter;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

// Boots the SEO tools: the site overview and its checks, the link check, the 404 monitor and the editor analysis.
class SeoServiceProvider extends ServiceProvider
{
    public const SYSTEM_GROUP = 'seo';

    public function register(): void
    {
        $this->app->singleton(SeoCheckRegistry::class, function (Application $app): SeoCheckRegistry {
            $registry = new SeoCheckRegistry;
            $registry->add(...$this->coreChecks($app));

            return $registry;
        });
        $this->app->bind(AuditContext::class, fn (Application $app): AuditContext => new AuditContext(
            settings: $app->make(Settings::class),
            seo: $app->make(SeoSettings::class),
            crawl: $app->make(CrawlState::class),
            types: $app->make(ContentTypeRegistry::class),
            maxItems: (int) config('verso.seo.audit.max_items'),
            chunk: (int) config('verso.seo.audit.chunk'),
        ));
        $this->app->singleton(SeoAudit::class, fn (Application $app): SeoAudit => new SeoAudit(
            checks: $app->make(SeoCheckRegistry::class),
            cache: $app->make('cache.store'),
            context: fn (): AuditContext => $app->make(AuditContext::class),
            ttl: (int) config('verso.seo.audit.cache_ttl'),
        ));
        $this->app->bind(CrawlState::class, fn (Application $app): CrawlState => new CrawlState(
            settings: $app->make(Settings::class),
            staleAfter: (int) config('verso.seo.crawl.stale_after'),
        ));
        $this->app->bind(LinkExtractor::class, fn (Application $app): LinkExtractor => new LinkExtractor(
            siteHosts: $this->siteHosts($app),
            maxLinks: (int) config('verso.seo.crawl.max_links'),
        ));
        $this->app->bind(LinkTargetResolver::class, fn (Application $app): LinkTargetResolver => new LinkTargetResolver(
            permalinks: $app->make(PermalinkResolver::class),
            router: $app->make('router'),
            publicPath: public_path(),
        ));
        $this->app->scoped(NotFoundMonitor::class, fn (Application $app): NotFoundMonitor => new NotFoundMonitor(
            limiter: $app->make(RateLimiter::class),
            enabled: (bool) config('verso.seo.not_found.enabled'),
            maxRows: (int) config('verso.seo.not_found.max_rows'),
            perMinute: (int) config('verso.seo.not_found.per_minute'),
            perVisitorPerMinute: (int) config('verso.seo.not_found.per_visitor_per_minute'),
            pathMaxLength: (int) config('verso.seo.not_found.path_max_length'),
            retentionDays: (int) config('verso.seo.not_found.retention_days'),
            ignore: array_values(array_map(strval(...), (array) config('verso.seo.not_found.ignore'))),
            secret: (string) config('app.key'),
        ));
        $this->app->bind(NotFoundSuggestions::class, fn (): NotFoundSuggestions => new NotFoundSuggestions(
            limit: (int) config('verso.seo.not_found.suggestions'),
            candidates: (int) config('verso.seo.not_found.suggestion_candidates'),
            minSimilarity: (int) config('verso.seo.not_found.suggestion_min_similarity'),
        ));

        $this->registerSystemSettings();
    }

    public function boot(): void
    {
        AdminRoutes::load(base_path('routes/verso/seo.php'));

        $this->registerAdminNav();
        $this->registerEditorPanel();
        $this->registerNotFoundMonitor();
        $this->registerAuditInvalidation();

        if ($this->app->runningInConsole()) {
            $this->commands([CheckLinksCommand::class, PruneNotFoundCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(PruneNotFoundCommand::class)->dailyAt((string) config('verso.seo.not_found.prune_at'))->withoutOverlapping();

            $cron = trim((string) config('verso.seo.crawl.schedule'));

            if ($cron !== '') {
                $schedule->command(CheckLinksCommand::class)->cron($cron)->withoutOverlapping();
            }
        });
    }

    /** @return list<SeoCheck> */
    protected function coreChecks(Application $app): array
    {
        return [
            $app->make(SearchDiscouraged::class),
            $app->make(SitemapOff::class),
            new NoSiteDescription,
            new BrokenLinks,
            new MissingDescription,
            new TitleLength((int) config('verso.seo.analysis.title_min'), (int) config('verso.seo.analysis.title_max')),
            new DuplicateTitles,
            new DuplicateDescriptions,
            new ImagesWithoutAlt,
            new NoindexInMenus,
            new MissingImage,
            new OrphanPages,
            new SlowPages((int) config('verso.seo.crawl.slow_ms')),
            new NotFoundPages,
        ];
    }

    // Children of Settings > Permalinks and SEO. People without manage_settings still see them at the top of Settings.
    protected function registerAdminNav(): void
    {
        AdminNav::add(new NavItem(key: 'seo.overview', label: 'SEO overview', route: 'admin.seo.index', icon: 'seo', order: 0, capability: Capability::ViewSeo, group: NavGroup::Settings, parent: 'site.permalinks', keywords: ['seo health', 'score', 'audit', 'broken links', 'alt text', 'posicionamiento', 'salud seo']));
        AdminNav::add(new NavItem(key: 'seo.not-found', label: '404 monitor', route: 'admin.seo.not-found.index', icon: 'redirects', order: 20, capability: Capability::ViewSeo, group: NavGroup::Settings, parent: 'site.permalinks', keywords: ['404', 'not found', 'missing pages', 'errors', 'página no encontrada']));
    }

    // The SEO card of the entry editor (component "site.seo"), after plugin panels. Its props hold the analysis settings.
    protected function registerEditorPanel(): void
    {
        $this->app->make(AdminSlotRegistry::class)->add(new SlotItem(
            slot: AdminSlot::EntrySidebar,
            key: 'seo',
            component: 'site.seo',
            order: 50,
            props: fn (Request $request): array => $this->app->make(EditorAnalysis::class)->props(),
        ));
    }

    // Public 404 answers are counted after the response is sent, so visitors never wait for it.
    protected function registerNotFoundMonitor(): void
    {
        Event::listen(function (RequestHandled $event): void {
            $monitor = $this->app->make(NotFoundMonitor::class);

            if ($monitor->shouldRecord($event->request, $event->response)) {
                $this->app->make(NotFoundMonitor::class)->queue($event->request);
            }
        });

        $this->app->terminating(fn () => $this->app->make(NotFoundMonitor::class)->flush());
    }

    // Saved content, settings, menus and media change the issue counts.
    protected function registerAuditInvalidation(): void
    {
        $flush = fn () => $this->app->make(SeoAudit::class)->flush();

        Event::listen([EntrySaved::class, EntryPublished::class, EntryDeleted::class, SiteSettingsUpdated::class], $flush);

        foreach ([Entry::class, Media::class, Menu::class, MenuItem::class] as $model) {
            $model::saved($flush);
            $model::deleted($flush);
        }
    }

    /** @return list<string> */
    protected function siteHosts(Application $app): array
    {
        $hosts = [
            parse_url((string) $app->make(Settings::class)->get('site.url'), PHP_URL_HOST),
            parse_url((string) config('app.url'), PHP_URL_HOST),
        ];

        return array_values(array_unique(array_map(strtolower(...), array_filter($hosts, fn (mixed $host): bool => is_string($host) && $host !== ''))));
    }

    // The limits of the SEO tools in System settings.
    protected function registerSystemSettings(): void
    {
        $registry = $this->app->make(ConfigRegistry::class);
        $group = self::SYSTEM_GROUP;
        $registry->addGroup(new ConfigGroup($group, 'SEO', 'Editor checks, the site overview, the link check and the 404 monitor.', 'seo', 70));

        $registry->add(
            new ConfigParameter('verso.seo.analysis.title_min', $group, 'Shortest good SEO title', 'Titles shorter than this get a tip in the editor and the overview.', unit: Unit::Characters, min: 5, max: 200, env: 'VERSO_SEO_TITLE_MIN'),
            new ConfigParameter('verso.seo.analysis.title_max', $group, 'Longest good SEO title', 'Search engines cut titles after about 60 characters.', unit: Unit::Characters, min: 20, max: 300, env: 'VERSO_SEO_TITLE_MAX'),
            new ConfigParameter('verso.seo.analysis.description_min', $group, 'Shortest good meta description', 'Descriptions shorter than this get a tip in the editor.', unit: Unit::Characters, min: 10, max: 300, env: 'VERSO_SEO_DESCRIPTION_MIN'),
            new ConfigParameter('verso.seo.analysis.description_max', $group, 'Longest good meta description', 'Search engines cut descriptions after about 160 characters.', unit: Unit::Characters, min: 50, max: 500, env: 'VERSO_SEO_DESCRIPTION_MAX'),
            new ConfigParameter('verso.seo.analysis.min_words', $group, 'Words for a full article', 'Posts with fewer words get a tip to write more.', unit: Unit::Items, min: 0, max: 5000, env: 'VERSO_SEO_MIN_WORDS'),
            new ConfigParameter('verso.seo.analysis.keyphrase_density_min', $group, 'Least keyphrase use', 'Share of the words that should be the focus keyphrase.', ParameterType::Decimal, Unit::Percent, min: 0, max: 10, env: 'VERSO_SEO_KEYPHRASE_DENSITY_MIN'),
            new ConfigParameter('verso.seo.analysis.keyphrase_density_max', $group, 'Most keyphrase use', 'More than this reads like keyword stuffing.', ParameterType::Decimal, Unit::Percent, min: 0.5, max: 20, env: 'VERSO_SEO_KEYPHRASE_DENSITY_MAX'),
            new ConfigParameter('verso.seo.analysis.sentence_max_words', $group, 'Words in a long sentence', 'Sentences longer than this count as long for the readability tips.', unit: Unit::Items, min: 8, max: 80, env: 'VERSO_SEO_SENTENCE_MAX_WORDS'),
            new ConfigParameter('verso.seo.analysis.paragraph_max_words', $group, 'Words in a long paragraph', 'Paragraphs longer than this get a tip to split them.', unit: Unit::Items, min: 30, max: 1000, env: 'VERSO_SEO_PARAGRAPH_MAX_WORDS'),
            new ConfigParameter('verso.seo.analysis.good_score', $group, 'Score for green', 'Entries at or over this score show a green light.', unit: Unit::Percent, min: 1, max: 100, env: 'VERSO_SEO_GOOD_SCORE'),
            new ConfigParameter('verso.seo.analysis.ok_score', $group, 'Score for amber', 'Entries at or over this score, but under green, show an amber light.', unit: Unit::Percent, min: 0, max: 99, env: 'VERSO_SEO_OK_SCORE'),
            new ConfigParameter('verso.seo.audit.cache_ttl', $group, 'Keep the overview counts', 'How long the SEO overview counts are kept before they are counted again. Saving content clears them.', unit: Unit::Seconds, min: 0, max: 86_400, env: 'VERSO_SEO_AUDIT_CACHE_TTL'),
            new ConfigParameter('verso.seo.crawl.batch_size', $group, 'Pages per link check step', 'Published pages the link check reads in one background job.', unit: Unit::Items, min: 1, max: 500, env: 'VERSO_SEO_CRAWL_BATCH_SIZE'),
            new ConfigParameter('verso.seo.crawl.slow_ms', $group, 'Slow page time', 'Pages whose content takes longer than this to build are listed as slow.', unit: Unit::Milliseconds, min: 10, max: 60_000, env: 'VERSO_SEO_SLOW_PAGE_MS'),
            new ConfigParameter('verso.seo.not_found.enabled', $group, '404 monitor', 'Count addresses visitors asked for that do not exist. No personal data is kept.', ParameterType::Boolean, env: 'VERSO_404_MONITOR'),
            new ConfigParameter('verso.seo.not_found.retention_days', $group, 'Keep 404 addresses', 'Days a missing address is kept after its last visit.', unit: Unit::Days, min: 1, max: 365, env: 'VERSO_404_RETENTION_DAYS'),
            new ConfigParameter('verso.seo.not_found.max_rows', $group, 'Most 404 addresses', 'New addresses are not added when the list is full.', unit: Unit::Items, min: 10, max: 100_000, env: 'VERSO_404_MAX_ROWS'),
            new ConfigParameter('verso.seo.not_found.per_minute', $group, '404 hits counted per minute', 'Most 404 hits counted per minute for the whole site. It stops floods from bots.', unit: Unit::Requests, min: 1, max: 10_000, env: 'VERSO_404_PER_MINUTE'),
        );
    }
}
