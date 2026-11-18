<?php

namespace App\Verso\Plugins\Actions;

use App\Verso\Plugins\Events\PluginDisabled;
use App\Verso\Plugins\Plugin;
use App\Verso\Plugins\PluginManager;

// Turns a plugin off and tells listeners about it.
class DisablePlugin
{
    public function __construct(protected PluginManager $plugins) {}

    public function handle(string $slug): Plugin
    {
        $wasEnabled = $this->plugins->isEnabled($slug);
        $plugin = $this->plugins->disable($slug);

        if ($wasEnabled) {
            PluginDisabled::dispatch($plugin);
        }

        return $plugin;
    }
}
