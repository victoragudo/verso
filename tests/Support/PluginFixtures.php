<?php

namespace Tests\Support;

use App\Verso\Plugins\PluginDiscovery;
use App\Verso\Plugins\PluginManager;
use App\Verso\Plugins\Support\PluginMigrator;

// Points the plugin system at the test plugins in tests/Fixtures/plugins.
class PluginFixtures
{
    public static function path(): string
    {
        return dirname(__DIR__).'/Fixtures/plugins';
    }

    public static function use(?string $path = null): PluginManager
    {
        config(['verso.plugins.paths' => [$path ?? self::path()]]);

        app()->forgetInstance(PluginDiscovery::class);
        app()->forgetInstance(PluginManager::class);

        return app(PluginManager::class);
    }

    // Turns on a plugin from the real plugins folder: provider, tables and routes.
    public static function boot(string $slug): PluginManager
    {
        $manager = self::use(base_path('plugins'));
        $manager->enable($slug);
        $plugin = $manager->get($slug);
        $manager->register($plugin);

        app(PluginMigrator::class)->migrate($plugin);

        $routes = app('router')->getRoutes();
        $routes->refreshNameLookups();
        $routes->refreshActionLookups();

        return $manager;
    }
}
