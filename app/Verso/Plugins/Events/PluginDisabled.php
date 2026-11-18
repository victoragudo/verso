<?php

namespace App\Verso\Plugins\Events;

use App\Verso\Plugins\Plugin;
use Illuminate\Foundation\Events\Dispatchable;

// Sent after a plugin has been disabled.
class PluginDisabled
{
    use Dispatchable;

    public function __construct(public readonly Plugin $plugin) {}
}
