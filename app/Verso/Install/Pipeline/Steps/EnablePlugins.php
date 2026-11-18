<?php

namespace App\Verso\Install\Pipeline\Steps;

use App\Verso\Install\Pipeline\Contracts\InstallStep;
use App\Verso\Install\Plan\InstallPlan;
use App\Verso\Plugins\Actions\EnablePlugin;
use Database\Seeders\PluginDemoSeeder;
use Illuminate\Contracts\Container\Container;

// Turns on the chosen plugins. Plugins that are on already stay on.
class EnablePlugins implements InstallStep
{
    public function __construct(
        protected EnablePlugin $enablePlugin,
        protected Container $container,
    ) {}

    public function key(): string
    {
        return 'plugins';
    }

    public function label(): string
    {
        return __('Turn on the plugins');
    }

    public function shouldRun(InstallPlan $plan): bool
    {
        return $plan->content->plugins !== [];
    }

    public function handle(InstallPlan $plan): void
    {
        foreach ($plan->content->plugins as $slug) {
            $this->enablePlugin->handle($slug);
        }

        // Demo content runs before this step, so plugins add their own demo content now.
        if ($plan->content->demo) {
            $seeder = $this->container->make(PluginDemoSeeder::class);
            $seeder->setContainer($this->container);
            $seeder->__invoke();
        }
    }
}
