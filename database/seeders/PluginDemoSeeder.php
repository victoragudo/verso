<?php

namespace Database\Seeders;

use App\Verso\Plugins\Contracts\SeedsDemoContent;
use App\Verso\Plugins\PluginManager;
use Illuminate\Database\Seeder;
use Illuminate\Routing\Router;

// Lets enabled plugins add their demo content (like the demo contact form) after the core demo content.
class PluginDemoSeeder extends Seeder
{
    public function run(PluginManager $plugins, Router $router): void
    {
        foreach ($plugins->all() as $plugin) {
            if (! $plugins->isEnabled($plugin->slug) || ! $plugin->canBeEnabled()) {
                continue;
            }

            $provider = $plugins->register($plugin);

            if ($provider instanceof SeedsDemoContent) {
                $provider->seedDemoContent();
            }
        }

        // Plugins registered now add routes late, so their names must be looked up again.
        $router->getRoutes()->refreshNameLookups();
        $router->getRoutes()->refreshActionLookups();
    }
}
