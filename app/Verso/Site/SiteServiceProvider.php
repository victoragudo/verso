<?php

namespace App\Verso\Site;

use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Content\Events\EntryDeleted;
use App\Verso\Content\Events\EntryPublished;
use App\Verso\Content\Events\EntrySaved;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Content\TaxonomyRegistry;
use App\Verso\Core\Admin\AdminNav;
use App\Verso\Core\Admin\NavGroup;
use App\Verso\Core\Admin\NavItem;
use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Routing\AdminRoutes;
use App\Verso\Core\Settings\Settings;
use App\Verso\Core\Support\SiteTime;
use App\Verso\Editor\BlockRegistry;
use App\Verso\Plugins\Blocks\PluginBlockAdapter;
use App\Verso\Plugins\FilterRegistry;
use App\Verso\Plugins\Filters\HeadTagsFilter;
use App\Verso\Plugins\View\HeadTag;
use App\Verso\Site\Actions\SyncMenuItems;
use App\Verso\Site\Actions\UpdatePermalinks;
use App\Verso\Site\Blocks\AnnouncementBarBlock;
use App\Verso\Site\Blocks\ColorSchemeToggleBlock;
use App\Verso\Site\Blocks\CopyrightBlock;
use App\Verso\Site\Blocks\LinkColumnsBlock;
use App\Verso\Site\Blocks\NavigationBlock;
use App\Verso\Site\Blocks\SearchBlock;
use App\Verso\Site\Blocks\SiteBlockRegistry;
use App\Verso\Site\Blocks\SiteLogoBlock;
use App\Verso\Site\Blocks\SiteTaglineBlock;
use App\Verso\Site\Blocks\SiteTitleBlock;
use App\Verso\Site\Blocks\SocialLinksBlock;
use App\Verso\Site\Events\SiteSettingsUpdated;
use App\Verso\Site\Listeners\InvalidateSitemap;
use App\Verso\Site\Listeners\NormalizeEntrySeo;
use App\Verso\Site\Listeners\NotifyIndexNow;
use App\Verso\Site\Menus\MenuCache;
use App\Verso\Site\Menus\MenuLocations;
use App\Verso\Site\Models\Menu as MenuModel;
use App\Verso\Site\Models\MenuItem;
use App\Verso\Site\Parts\SitePartStore;
use App\Verso\Site\Permalinks\PermalinkClashes;
use App\Verso\Site\Permalinks\Permalinks;
use App\Verso\Site\Permalinks\PermalinkSettings;
use App\Verso\Site\Seo\FaqSchema;
use App\Verso\Site\Sitemap\SitemapBuilder;
use App\Verso\Site\Sitemap\SitemapCache;
use App\Verso\Site\Sitemap\SitemapSettings;
use App\Verso\Site\View\Components\Menu;
use App\Verso\Site\View\Components\Seo;
use App\Verso\Theme\Support\ThemeText;
use App\Verso\Theme\ThemeManager;
use Illuminate\Cache\Events\ForgettingKey;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

// Boots the site module: general settings, SEO, sitemap, robots.txt, menus and redirects.
class SiteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(Permalinks::class, fn (Application $app): Permalinks => new Permalinks(
            settings: $app->make(PermalinkSettings::class),
            types: $app->make(ContentTypeRegistry::class),
            taxonomies: $app->make(TaxonomyRegistry::class),
            time: $app->make(SiteTime::class),
            categoryTaxonomy: (string) config('verso.permalinks.category_taxonomy'),
            uncategorized: (string) config('verso.permalinks.uncategorized'),
        ));
        $this->app->bind(UpdatePermalinks::class, fn (Application $app): UpdatePermalinks => new UpdatePermalinks(
            settings: $app->make(Settings::class),
            permalinks: $app->make(PermalinkSettings::class),
            types: $app->make(ContentTypeRegistry::class),
            taxonomies: $app->make(TaxonomyRegistry::class),
            previousKept: (int) config('verso.permalinks.previous_kept'),
        ));
        $this->app->bind(PermalinkClashes::class, fn (Application $app): PermalinkClashes => new PermalinkClashes(
            types: $app->make(ContentTypeRegistry::class),
            taxonomies: $app->make(TaxonomyRegistry::class),
            router: $app->make('router'),
            reserved: array_values(array_map(strval(...), (array) config('verso.permalinks.reserved_paths'))),
        ));
        $this->app->singleton(MenuLocations::class, function (Application $app): MenuLocations {
            $locations = new MenuLocations(array_map(strval(...), (array) config('verso.menus.locations')));
            $locations->defer(
                fn (): array => $this->themeMenuLocations($app),
                fn (string $label): string => $app->make(ThemeText::class)->text($app->make(ThemeManager::class)->active(), $label),
            );

            return $locations;
        });
        $this->app->bind(SyncMenuItems::class, fn (): SyncMenuItems => new SyncMenuItems(
            maxDepth: (int) config('verso.menus.max_depth'),
        ));
        $this->app->singleton(MenuCache::class, fn (Application $app): MenuCache => new MenuCache(
            cache: $app->make('cache.store'),
            prefix: (string) config('verso.menus.cache_prefix'),
            ttl: (int) config('verso.menus.cache_ttl'),
        ));
        $this->app->singleton(SiteBlockRegistry::class);
        $this->app->bind(FaqSchema::class, fn (): FaqSchema => new FaqSchema((int) config('verso.seo.faq_max_questions')));
        $this->app->scoped(SitePartStore::class, fn (): SitePartStore => new SitePartStore(
            maxRevisions: (int) config('verso.site_parts.max_revisions'),
        ));
        $this->app->singleton(SitemapCache::class, fn (Application $app): SitemapCache => new SitemapCache(
            cache: $app->make('cache.store'),
            prefix: (string) config('verso.sitemap.cache_prefix'),
            ttl: (int) config('verso.sitemap.cache_ttl'),
        ));
        $this->app->bind(SitemapBuilder::class, function (Application $app): SitemapBuilder {
            $options = $app->make(SitemapSettings::class);
            $types = $app->make(ContentTypeRegistry::class);
            $taxonomies = $app->make(TaxonomyRegistry::class);
            $configTypes = array_values(array_map(strval(...), (array) config('verso.sitemap.exclude_types')));
            $configTaxonomies = array_values(array_map(strval(...), (array) config('verso.sitemap.exclude_taxonomies')));

            // Saved lists name what is included. Without them, config decides what is left out.
            return new SitemapBuilder(
                types: $types,
                time: $app->make(SiteTime::class),
                maxUrls: $options->maxUrls(),
                excludedTypes: $options->types() === null ? $configTypes : array_values(array_diff($types->names(), $options->types())),
                taxonomies: $taxonomies,
                excludedTaxonomies: $options->taxonomies() === null ? $configTaxonomies : array_values(array_diff(array_keys($taxonomies->all()), $options->taxonomies())),
                excludedEntries: $options->excludedEntries(),
                includeImages: $options->includeImages(),
                lastModified: $options->lastModified(),
            );
        });
    }

    // Blocks for building headers and footers. They are shared with the editor like plugin blocks.
    public const array SITE_BLOCKS = [
        SiteLogoBlock::class, SiteTitleBlock::class, SiteTaglineBlock::class, NavigationBlock::class, SearchBlock::class,
        SocialLinksBlock::class, LinkColumnsBlock::class, CopyrightBlock::class, AnnouncementBarBlock::class,
        ColorSchemeToggleBlock::class,
    ];

    public function boot(): void
    {
        $this->registerSiteBlocks();
        $this->registerColorSchemeHead();
        AdminRoutes::load(base_path('routes/verso/site.php'));
        Route::group([], base_path('routes/verso/site-public.php'));

        $this->loadViewsFrom(resource_path('views/verso/site'), 'verso-site');
        Blade::component('verso-seo', Seo::class);
        Blade::component('verso-menu', Menu::class);

        Entry::saving(fn (Entry $entry) => $this->app->make(NormalizeEntrySeo::class)->handle($entry));
        Event::listen([EntrySaved::class, EntryPublished::class, EntryDeleted::class, SiteSettingsUpdated::class], InvalidateSitemap::class);
        Event::listen(EntryPublished::class, NotifyIndexNow::class);
        Term::saved(fn () => $this->app->make(SitemapCache::class)->flush());
        Term::deleted(fn () => $this->app->make(SitemapCache::class)->flush());

        $this->registerMenuCacheInvalidation();
        $this->registerAdminNav();
    }

    // Menus show entry and term titles and URLs, and the front page setting changes a URL.
    protected function registerMenuCacheInvalidation(): void
    {
        $flush = fn () => $this->app->make(MenuCache::class)->flush();

        Event::listen([EntrySaved::class, EntryPublished::class, EntryDeleted::class, SiteSettingsUpdated::class], $flush);
        Event::listen(function (ForgettingKey $event) use ($flush): void {
            if ($event->key === config('verso.settings.cache_key')) {
                $flush();
            }
        });

        foreach ([MenuModel::class, MenuItem::class, Term::class] as $model) {
            $model::saved($flush);
            $model::deleted($flush);
        }
    }

    protected function registerAdminNav(): void
    {
        AdminNav::add(new NavItem(key: 'site.settings', label: 'General', route: 'admin.site.settings.edit', icon: 'settings', order: 10, capability: Capability::ManageGeneralSettings, group: NavGroup::Settings, keywords: ['site name', 'tagline', 'language', 'timezone', 'reading', 'ajustes', 'general']));
        AdminNav::add(new NavItem(key: 'site.permalinks', label: 'Permalinks and SEO', route: 'admin.site.permalinks.edit', icon: 'seo', order: 20, capability: Capability::ManagePermalinks, group: NavGroup::Settings, keywords: ['urls', 'slugs', 'search engines', 'sitemap', 'robots', 'enlaces']));
        AdminNav::add(new NavItem(key: 'site.redirects', label: 'Redirects', route: 'admin.site.redirects.index', icon: 'redirects', order: 10, capability: Capability::ManagePermalinks, group: NavGroup::Settings, parent: 'site.permalinks', keywords: ['301', '302', 'moved', 'redirecciones']));
        AdminNav::add(new NavItem(key: 'site.editor', label: 'Site Editor', route: 'admin.site-editor', icon: 'siteEditor', order: 10, capability: Capability::EditSite, group: NavGroup::Design, keywords: ['header', 'footer', 'templates', 'layout', 'editor del sitio']));
        AdminNav::add(new NavItem(key: 'site.menus', label: 'Menus', route: 'admin.site.menus.index', icon: 'menus', order: 30, capability: Capability::ManageMenus, group: NavGroup::Design, keywords: ['navigation', 'links', 'menús']));
    }

    /**
     * Menu locations the active theme declares under "menus" in theme.json.
     *
     * @return array<string, string>
     */
    protected function themeMenuLocations(Application $app): array
    {
        $locations = [];

        foreach ($app->make(ThemeManager::class)->active()->menus as $name => $label) {
            if (is_string($name) && preg_match('/^[a-z][a-z0-9_-]{0,40}$/', $name) === 1 && is_string($label)) {
                $locations[$name] = $label;
            }
        }

        return $locations;
    }

    // When a published part has the dark mode switch, the head gets the tiny script that applies the saved choice early.
    protected function registerColorSchemeHead(): void
    {
        $this->app->make(FilterRegistry::class)->add(HeadTagsFilter::class, function (array $tags): array {
            $theme = $this->app->make(ThemeManager::class)->active()->slug;

            if ($this->app->make(SitePartStore::class)->uses($theme, ColorSchemeToggleBlock::NAME)) {
                $tags[] = HeadTag::script(code: ColorSchemeToggleBlock::headScript());
            }

            return $tags;
        });
    }

    protected function registerSiteBlocks(): void
    {
        $siteBlocks = $this->app->make(SiteBlockRegistry::class);
        $blocks = $this->app->make(BlockRegistry::class);

        foreach (self::SITE_BLOCKS as $class) {
            $block = $this->app->make($class);
            $siteBlocks->register($block);
            $blocks->register(new PluginBlockAdapter($block));
        }
    }
}
