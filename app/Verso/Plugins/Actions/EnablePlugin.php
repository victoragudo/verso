<?php

namespace App\Verso\Plugins\Actions;

use App\Verso\Core\Admin\NavNewBadges;
use App\Verso\Plugins\Events\PluginEnabled;
use App\Verso\Plugins\Exceptions\PluginCannotBeEnabled;
use App\Verso\Plugins\Plugin;
use App\Verso\Plugins\PluginManager;
use App\Verso\Plugins\Support\PluginMigrator;
use Throwable;

// Turns a plugin on, runs its migrations and tells listeners about it.
class EnablePlugin
{
    public function __construct(
        protected PluginManager $plugins,
        protected PluginMigrator $migrator,
        protected NavNewBadges $badges,
    ) {}

    public function handle(string $slug): Plugin
    {
        $wasEnabled = $this->plugins->isEnabled($slug);
        $plugin = $this->plugins->enable($slug);

        try {
            if ($this->migrator->runsOnEnable()) {
                $this->migrator->migrate($plugin);
            }
        } catch (Throwable $exception) {
            if (! $wasEnabled) {
                $this->plugins->disable($slug);
            }

            throw PluginCannotBeEnabled::because($slug, ['Its database migrations failed ('.$exception->getMessage().').']);
        }

        if (! $wasEnabled) {
            $this->badges->pluginEnabled($slug);
            PluginEnabled::dispatch($plugin);
        }

        return $plugin;
    }
}
