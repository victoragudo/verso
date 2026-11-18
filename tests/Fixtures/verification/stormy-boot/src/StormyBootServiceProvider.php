<?php

namespace Verso\Plugins\StormyBoot;

use App\Verso\Plugins\PluginServiceProvider;
use RuntimeException;

// Fixture plugin whose provider throws while booting.
class StormyBootServiceProvider extends PluginServiceProvider
{
    public function boot(): void
    {
        throw new RuntimeException('The storm warning feed URL is missing.');
    }
}
