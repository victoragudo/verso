<?php

namespace Tests\Support;

use App\Verso\Plugins\Actions\EnablePlugin;
use App\Verso\Plugins\PluginManager;
use Illuminate\Contracts\Console\Kernel;
use Verso\Plugins\Analytics\Console\RollupAnalyticsCommand;
use Verso\Plugins\Analytics\Models\AnalyticsEvent;

// Turns on the bundled Analytics plugin for a test, with its tables and routes.
class AnalyticsPlugin
{
    // A desktop browser user agent used by most tests.
    public const FIREFOX = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14.6; rv:132.0) Gecko/20100101 Firefox/132.0';

    public const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1';

    public static function boot(): PluginManager
    {
        $manager = PluginFixtures::use(base_path('plugins'));

        app(EnablePlugin::class)->handle('analytics');
        $manager->register($manager->get('analytics'));

        // The app booted before the plugin, so route names must be indexed again.
        app('router')->getRoutes()->refreshNameLookups();
        app('router')->getRoutes()->refreshActionLookups();

        // Artisan already started too, so the plugin command is added by hand.
        app(Kernel::class)->registerCommand(app(RollupAnalyticsCommand::class));

        return $manager;
    }

    /**
     * Save a raw event straight in the table.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function event(array $attributes = []): AnalyticsEvent
    {
        return AnalyticsEvent::query()->create([
            'day' => '2026-11-17',
            'path' => '/blog/a-weekend-in-the-douro-valley',
            'referrer_host' => null,
            'device' => 'desktop',
            'visitor' => hash('sha256', 'reader-lisbon'),
            'created_at' => now(),
            ...$attributes,
        ]);
    }
}
