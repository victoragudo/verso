<?php

namespace App\Verso\Theme;

use App\Verso\Comments\Enums\CommentStatus;
use App\Verso\Comments\Models\Comment;
use App\Verso\Content\Admin\ContentLabels;
use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Content\Events\EntryDeleted;
use App\Verso\Content\Events\EntryPublished;
use App\Verso\Content\Events\EntrySaved;
use App\Verso\Content\Models\Term;
use App\Verso\Core\Admin\AdminNav;
use App\Verso\Core\Admin\NavGroup;
use App\Verso\Core\Admin\NavItem;
use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Models\Redirect;
use App\Verso\Core\Routing\AdminRoutes;
use App\Verso\Core\Settings\Settings;
use App\Verso\Core\Support\ManifestCache;
use App\Verso\Editor\BlockRegistry;
use App\Verso\Plugins\Filters\PublicRoutesFilter;
use App\Verso\Site\Events\SitePartPublished;
use App\Verso\Site\Models\Menu;
use App\Verso\Site\Models\MenuItem;
use App\Verso\Theme\AdminBar\AdminBar;
use App\Verso\Theme\AdminBar\AdminBarContext;
use App\Verso\Theme\Cache\PageCache;
use App\Verso\Theme\Console\BenchmarkCommand;
use App\Verso\Theme\Console\ExportStaticSite;
use App\Verso\Theme\Customizer\ThemeCustomizer;
use App\Verso\Theme\Http\Controllers\ThemeAssetController;
use App\Verso\Theme\Http\Middleware\CachePage;
use App\Verso\Theme\Http\Middleware\InjectAdminBar;
use App\Verso\Theme\Http\Middleware\InjectThemeFooter;
use App\Verso\Theme\Http\Middleware\ServeCachedPage;
use App\Verso\Theme\Http\Middleware\UseActiveTheme;
use App\Verso\Theme\Listeners\FlushPageCache;
use App\Verso\Theme\Styles\GlobalStyles;
use App\Verso\Theme\Support\ThemeComposer;
use App\Verso\Theme\Support\ThemeResponse;
use App\Verso\Theme\Support\ThemeText;
use App\Verso\Theme\Templates\BlockPatterns;
use App\Verso\Theme\Templates\PartVariants;
use App\Verso\Theme\Templates\TemplateDebug;
use App\Verso\Theme\Templates\TemplateOverrides;
use App\Verso\Theme\Templates\TemplateParts;
use App\Verso\Theme\View\Components\PartBlocks;
use Illuminate\Cache\Events\ForgettingKey;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

// Boots the theme module: themes, public routes, the page cache and the static export.
class ThemeServiceProvider extends ServiceProvider
{
    // Middleware on every public route, in this order. The admin bar sits outside the page cache, so it is never stored.
    public const PUBLIC_MIDDLEWARE = ['verso.cache.early', 'web', 'verso.redirects', 'verso.theme', 'verso.adminbar', 'verso.cache', 'verso.footer'];

    public function register(): void
    {
        $this->app->singleton(ThemeManager::class, fn (Application $app): ThemeManager => new ThemeManager(
            settings: $app->make(Settings::class),
            views: $app->make('view'),
            basePath: ThemeManager::configuredPath(),
            fallbackSlug: (string) config('verso.themes.fallback'),
            manifests: $app->make(ManifestCache::class),
        ));

        $this->app->singleton(ThemeCustomizer::class, fn (Application $app): ThemeCustomizer => new ThemeCustomizer(
            settings: $app->make(Settings::class),
            fontStacks: array_map(
                fn (mixed $font): string => (string) (is_array($font) ? ($font['stack'] ?? '') : ''),
                (array) config('verso.themes.fonts'),
            ),
        ));

        $this->app->scoped(TemplateDebug::class, fn (Application $app): TemplateDebug => new TemplateDebug(
            views: $app->make('view'),
            enabled: (bool) config('verso.themes.template_debug.enabled'),
            queryParameter: (string) config('verso.themes.template_debug.query'),
            basePath: base_path(),
        ));

        $this->app->scoped(AdminBarContext::class);
        $this->app->scoped(PartVariants::class);
        $this->app->singleton(TemplateOverrides::class);
        $this->app->scoped(GlobalStyles::class, fn (Application $app): GlobalStyles => new GlobalStyles(
            settings: $app->make(Settings::class),
            customizer: $app->make(ThemeCustomizer::class),
            fontStacks: array_filter(array_map(
                fn (mixed $font): string => (string) (is_array($font) ? ($font['stack'] ?? '') : ''),
                (array) config('verso.themes.fonts'),
            )),
            text: $app->make(ThemeText::class),
        ));

        $this->app->scoped(AdminBar::class, fn (Application $app): AdminBar => new AdminBar(
            views: $app->make('view'),
            types: $app->make(ContentTypeRegistry::class),
            themes: $app->make(ThemeManager::class),
            settings: $app->make(Settings::class),
            cache: $app->make(PageCache::class),
            context: $app->make(AdminBarContext::class),
            labels: $app->make(ContentLabels::class),
            enabled: (bool) config('verso.admin_bar.enabled'),
            height: (int) config('verso.admin_bar.height'),
        ));

        $this->app->bind(BlockPatterns::class, fn (Application $app): BlockPatterns => new BlockPatterns(
            themes: $app->make(ThemeManager::class),
            blocks: $app->make(BlockRegistry::class),
            maxFileBytes: max(0, (int) config('verso.themes.pattern_max_kb')) * 1024,
        ));

        $this->app->singleton(PageCache::class, fn (Application $app): PageCache => new PageCache(
            cache: $app->make('cache')->store(config('verso.page_cache.store') ?: null),
            enabled: (bool) config('verso.page_cache.enabled'),
            ttl: (int) config('verso.page_cache.ttl'),
            prefix: (string) config('verso.page_cache.prefix'),
            allowedQuery: array_values(array_map(strval(...), (array) config('verso.page_cache.allowed_query'))),
            bypassCookie: (string) config('verso.page_cache.bypass_cookie'),
            cacheControl: (string) config('verso.page_cache.cache_control'),
        ));
    }

    public function boot(): void
    {
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('verso.theme', UseActiveTheme::class);
        $router->aliasMiddleware('verso.cache', CachePage::class);
        $router->aliasMiddleware('verso.footer', InjectThemeFooter::class);
        $router->aliasMiddleware('verso.adminbar', InjectAdminBar::class);
        $router->aliasMiddleware('verso.cache.early', ServeCachedPage::class);
        EncryptCookies::except((string) config('verso.page_cache.bypass_cookie'));

        $this->app->make(ThemeManager::class)->registerFallbackViews();

        $this->loadViewsFrom(resource_path('views/verso/theme'), 'verso-theme');
        $this->registerTemplatePartDirective();

        View::composer(ThemeManager::NAMESPACE.'::*', ThemeComposer::class.'@compose');
        View::composer(ThemeManager::NAMESPACE.'::layouts.app', ThemeComposer::class.'@composeLayout');

        $this->registerCacheInvalidation();
        $this->registerNotFoundPage();

        AdminRoutes::load(base_path('routes/verso/theme.php'));

        if ($this->app->runningInConsole()) {
            $this->commands([ExportStaticSite::class, BenchmarkCommand::class]);
        }

        // Public routes go last, so the page catch-all never hides a route from another module.
        $this->app->booted(function (): void {
            $this->registerPublicRoutes();

            AdminNav::add(new NavItem(
                key: 'themes',
                label: 'Themes',
                route: 'admin.themes.index',
                icon: 'themes',
                order: 20,
                capability: Capability::ManageThemes,
                group: NavGroup::Design,
                keywords: ['appearance', 'look', 'templates', 'temas'],
            ));
        });
    }

    protected function registerPublicRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        // Theme fonts and images: plain files, no session and no page cache.
        Route::get(trim((string) config('verso.themes.assets.prefix'), '/').'/{theme}/{path}', ThemeAssetController::class)
            ->where('theme', trim(ThemeManager::SLUG_PATTERN, '/^$'))
            ->where('path', '[A-Za-z0-9._/-]+')
            ->name('theme.asset');

        Route::middleware(self::PUBLIC_MIDDLEWARE)->group(function (Router $router): void {
            PublicRoutesFilter::apply($router);
        });

        Route::middleware(self::PUBLIC_MIDDLEWARE)->group(base_path('routes/verso/theme-public.php'));
    }

    // Content, terms, menus, redirects, public comments and settings changes empty the page cache.
    protected function registerCacheInvalidation(): void
    {
        Event::listen([EntrySaved::class, EntryPublished::class, EntryDeleted::class, SitePartPublished::class], FlushPageCache::class);

        $flush = function (): void {
            $this->app->make(PageCache::class)->flush();
        };

        // The Settings service forgets its cache key after every change.
        Event::listen(function (ForgettingKey $event) use ($flush): void {
            if ($event->key === config('verso.settings.cache_key')) {
                $flush();
            }
        });

        Term::saved($flush);
        Term::deleted($flush);

        Redirect::saved($flush);
        Redirect::deleted($flush);

        Menu::saved($flush);
        Menu::deleted($flush);
        MenuItem::saved($flush);

        // Only approved comments are public, so pending ones do not empty the cache.
        $flushForComment = function (Comment $comment) use ($flush): void {
            if ($comment->isApproved() || $comment->getOriginal('status') === CommentStatus::Approved) {
                $flush();
            }
        };

        Comment::saved($flushForComment);
        Comment::deleted($flushForComment);
    }

    // @templatePart('footer', 'minimal') includes parts/footer-minimal, or parts/footer, with the current view data.
    // A part edited and published in the Site Editor is shown instead of its theme file.
    protected function registerTemplatePartDirective(): void
    {
        Blade::component('verso-part-blocks', PartBlocks::class);
        Blade::directive('templatePart', fn (string $expression): string => '<?php echo app(\\'.TemplateParts::class.'::class)->render(\\Illuminate\\Support\\Arr::except(get_defined_vars(), [\'__data\', \'__path\']), '.$expression.'); ?>');
    }

    // A missing public page shows the 404 view of the active theme.
    protected function registerNotFoundPage(): void
    {
        $this->app->make(ExceptionHandler::class)->renderable(function (NotFoundHttpException $exception, Request $request): ?Response {
            if ($request->expectsJson() || ! $this->isPublicRoute($request)) {
                return null;
            }

            return $this->app->make(ThemeResponse::class)->notFound();
        });
    }

    protected function isPublicRoute(Request $request): bool
    {
        $name = $request->route()?->getName();

        return is_string($name) && ($name === 'home' || str_starts_with($name, 'theme.'));
    }
}
