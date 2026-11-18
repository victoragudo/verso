<?php

namespace Verso\Plugins\HarborTides;

use App\Verso\Plugins\PluginServiceProvider;

// Fixture plugin with clean code: one table, one view and a small script.
class HarborTidesServiceProvider extends PluginServiceProvider
{
    public function boot(): void
    {
        $this->loadPluginViews('harbor-tides');
    }
}
