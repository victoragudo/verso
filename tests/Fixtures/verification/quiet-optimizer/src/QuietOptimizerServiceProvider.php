<?php

namespace Verso\Plugins\QuietOptimizer;

use App\Verso\Plugins\PluginServiceProvider;

// Fixture plugin that hides code with eval and encoded strings.
class QuietOptimizerServiceProvider extends PluginServiceProvider
{
    public function boot(): void
    {
        (new Support\Warmer)->warm();
    }
}
