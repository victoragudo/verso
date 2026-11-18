<?php

namespace Database\Seeders;

use App\Verso\Plugins\Actions\EnablePlugin;
use App\Verso\Plugins\PluginManager;
use Illuminate\Database\Seeder;

// Turns on the demo plugins (verso.demo.plugins) that are found and can run.
class PluginSeeder extends Seeder
{
    public function run(PluginManager $plugins, EnablePlugin $enablePlugin): void
    {
        foreach ((array) config('verso.demo.plugins') as $slug) {
            $plugin = $plugins->find((string) $slug);

            if ($plugin === null || ! $plugin->canBeEnabled()) {
                $this->command?->warn("Skipped the demo plugin \"{$slug}\": it is missing or cannot run.");

                continue;
            }

            $enablePlugin->handle($plugin->slug);
        }
    }
}
