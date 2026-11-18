<?php

namespace App\Verso\Plugins;

use App\Verso\Api\ApiServiceProvider;
use App\Verso\Core\Admin\AdminNav;
use App\Verso\Core\Admin\NavGroup;
use App\Verso\Core\Admin\NavItem;
use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Routing\AdminRoutes;
use App\Verso\Core\Settings\Settings;
use App\Verso\Core\Support\ManifestCache;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Install\InstallServiceProvider;
use App\Verso\Media\Models\Media;
use App\Verso\Plugins\Blocks\PluginBlockRegistry;
use App\Verso\Plugins\Console\MakeBlockCommand;
use App\Verso\Plugins\Console\MakePluginCommand;
use App\Verso\Plugins\Console\MakeThemeCommand;
use App\Verso\Plugins\EntryPanels\EntryPanelRegistry;
use App\Verso\Plugins\Events\PluginDisabled;
use App\Verso\Plugins\Events\PluginEnabled;
use App\Verso\Plugins\Listeners\FilterOutgoingMail;
use App\Verso\Plugins\Slots\AdminSlotRegistry;
use App\Verso\Plugins\Support\PluginConfig;
use App\Verso\Plugins\Support\PluginMigrator;
use App\Verso\Plugins\View\ThemeFooter;
use App\Verso\Plugins\View\ThemeHead;
use Illuminate\Cache\Events\ForgettingKey;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Throwable;

// Boots plugins, the public API and the installer. Enabled plugin providers are registered here.
class PluginsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FilterRegistry::class);
        $this->app->singleton(PluginBlockRegistry::class);
        $this->app->singleton(EntryPanelRegistry::class);
        $this->app->singleton(AdminSlotRegistry::class);
        $this->app->singleton(PluginDiscovery::class, fn (Application $app): PluginDiscovery => new PluginDiscovery(
            paths: array_values(array_map(strval(...), (array) config('verso.plugins.paths'))),
            manifestName: (string) config('verso.plugins.manifest'),
            versoVersion: (string) config('verso.version'),
            composerInstalled: config('verso.plugins.composer_installed') !== null ? (string) config('verso.plugins.composer_installed') : null,
            manifests: $app->make(ManifestCache::class),
        ));
        $this->app->singleton(PluginManager::class, fn (Application $app): PluginManager => new PluginManager(
            app: $app,
            discovery: $app->make(PluginDiscovery::class),
            settings: $app->make(Settings::class),
        ));

        $this->app->singleton(BlockOutputCache::class, fn (Application $app): BlockOutputCache => new BlockOutputCache(
            cache: config('verso.block_cache.enabled') ? $app->make('cache')->store(config('verso.block_cache.store') ?: null) : null,
            prefix: (string) config('verso.block_cache.prefix'),
            ttl: (int) config('verso.block_cache.ttl'),
        ));
        $this->app->bind(ContentRenderer::class, fn (Application $app): ContentRenderer => new ContentRenderer(
            blocks: $app->make(BlockRenderer::class),
            pluginBlocks: $app->make(PluginBlockRegistry::class),
            cache: $app->make(BlockOutputCache::class),
        ));

        $this->app->singleton(PluginMigrator::class, fn (Application $app): PluginMigrator => new PluginMigrator(
            migrator: $app->make('migrator'),
            relativePath: (string) config('verso.plugins.migrations_path'),
            runOnEnable: (bool) config('verso.plugins.migrate_on_enable'),
        ));

        $this->app->singleton(PluginConfig::class, fn (Application $app): PluginConfig => new PluginConfig(
            app: $app,
            config: $app->make('config'),
            relativePath: (string) config('verso.plugins.config_path'),
        ));

        $this->app->register(ApiServiceProvider::class);
        $this->app->register(InstallServiceProvider::class);

        // Other providers (like the cache) must be registered before plugins read their settings.
        if ($this->app->isBooted()) {
            $this->registerEnabledPlugins();
        } else {
            $this->app->booting(fn () => $this->registerEnabledPlugins());
        }
    }

    public function boot(): void
    {
        AdminRoutes::load(base_path('routes/verso/plugins.php'));

        Blade::component('verso-footer', ThemeFooter::class);
        Blade::component('verso-head', ThemeHead::class);

        Event::listen(MessageSending::class, FilterOutgoingMail::class);

        $this->registerBlockCacheInvalidation();

        $this->app->booted(fn () => AdminNav::add(new NavItem(
            key: 'plugins',
            label: 'Plugins and themes',
            route: 'admin.plugins.index',
            icon: 'plugins',
            order: 20,
            capability: Capability::ActivatePlugins,
            group: NavGroup::Administration,
            keywords: ['extensions', 'add-ons', 'install', 'marketplace', 'complementos', 'extensiones'],
        )));

        if ($this->app->runningInConsole()) {
            $this->commands([MakePluginCommand::class, MakeBlockCommand::class, MakeThemeCommand::class]);
        }
    }

    // The settings table may not exist yet (fresh install), so a failure means "no plugins".
    public function registerEnabledPlugins(): void
    {
        $this->prepareEloquent();
        $this->loadAllPluginConfig();

        try {
            $this->app->make(PluginManager::class)->registerEnabled();
        } catch (Throwable) {
            return;
        }
    }

    // In the console (where "config:cache" runs) every plugin config is loaded, so the cache holds them all.
    protected function loadAllPluginConfig(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        try {
            $this->app->make(PluginConfig::class)->mergeAll($this->app->make(PluginManager::class)->all());
        } catch (Throwable) {
            return;
        }
    }

    // Plugins load before the database provider boots. Settings use Eloquent when the cache is cold, so connect it early.
    protected function prepareEloquent(): void
    {
        if (Model::getConnectionResolver() === null && $this->app->bound('db')) {
            Model::setConnectionResolver($this->app->make('db'));
            Model::setEventDispatcher($this->app->make('events'));
        }
    }

    // Image blocks show media details, and plugins or settings can change how blocks render.
    protected function registerBlockCacheInvalidation(): void
    {
        $flush = fn () => $this->app->make(BlockOutputCache::class)->flush();

        Media::saved($flush);
        Media::deleted($flush);
        Event::listen([PluginEnabled::class, PluginDisabled::class], $flush);
        Event::listen(function (ForgettingKey $event) use ($flush): void {
            if ($event->key === config('verso.settings.cache_key')) {
                $flush();
            }
        });
    }
}
