<?php

namespace App\Verso\Layouts;

use App\Verso\Access\Support\AccessAbilities;
use App\Verso\Blocks\BlocksServiceProvider;
use App\Verso\Core\Admin\AdminNav;
use App\Verso\Core\Admin\NavGroup;
use App\Verso\Core\Admin\NavItem;
use App\Verso\Core\Routing\AdminRoutes;
use App\Verso\Editor\Assets\BlockAssetRegistry;
use App\Verso\Editor\Support\RenderContext;
use App\Verso\Layouts\Blocks\PopupButtonBlock;
use App\Verso\Layouts\Conditions\ConditionRegistry;
use App\Verso\Layouts\Conditions\CoreConditions;
use App\Verso\Plugins\FilterRegistry;
use App\Verso\Plugins\Filters\ThemeFooterFilter;
use App\Verso\Theme\Cache\PageCache;
use App\Verso\Theme\Templates\TemplateOverrides;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

// Boots the theme builder: layouts for templates, headers, footers and popups, with conditions in a registry.
// Layouts are drawn through the theme template overrides, so themes need no changes.
class LayoutsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConditionRegistry::class, function (): ConditionRegistry {
            $registry = new ConditionRegistry;
            CoreConditions::register($registry);

            return $registry;
        });
        $this->app->scoped(LayoutStore::class, fn (): LayoutStore => new LayoutStore((int) config('verso.layouts.max_revisions')));
        $this->app->scoped(LayoutResolver::class, fn (Application $app): LayoutResolver => new LayoutResolver(
            store: $app->make(LayoutStore::class),
            conditions: $app->make(ConditionRegistry::class),
            render: $app->make(RenderContext::class),
        ));
        $this->app->scoped(LayoutRenderer::class);
        $this->app->scoped(PopupRenderer::class);
    }

    public function boot(): void
    {
        AdminRoutes::load(base_path('routes/verso/layouts.php'));
        $this->loadViewsFrom(resource_path('views/verso/layouts'), 'verso-layouts');

        $overrides = $this->app->make(TemplateOverrides::class);
        $overrides->page('layouts', fn ($candidates, array $data): ?string => $this->app->make(LayoutRenderer::class)->template($candidates, $data));
        $overrides->partOverride('layouts', fn (string $name, array $data, \Closure $theme): ?string => $this->app->make(LayoutRenderer::class)->part($name, $data, $theme));

        $this->app->make(FilterRegistry::class)->add(ThemeFooterFilter::class, fn (string $html): string => $html.$this->app->make(PopupRenderer::class)->footer());

        $assets = $this->app->make(BlockAssetRegistry::class);

        foreach ([LayoutRenderer::HEADER_ASSET, PopupRenderer::ASSET] as $key) {
            BlocksServiceProvider::registerAsset($assets, $key, []);
        }

        BlocksServiceProvider::add($this->app->make(PopupButtonBlock::class));

        Event::listen(LayoutPublished::class, fn () => $this->app->make(PageCache::class)->flush());

        AdminNav::add(new NavItem(key: 'site.theme-builder', label: 'Theme builder', route: 'admin.theme-builder.index', icon: 'siteEditor', order: 15, capability: AccessAbilities::LAYOUTS, group: NavGroup::Design, keywords: ['templates', 'header', 'footer', 'popup', 'banner', 'cookie notice', 'mega menu', 'plantillas']));
    }
}
