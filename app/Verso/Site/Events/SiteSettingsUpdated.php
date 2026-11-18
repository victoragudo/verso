<?php

namespace App\Verso\Site\Events;

use Illuminate\Foundation\Events\Dispatchable;

// Fired after the general site settings are saved.
class SiteSettingsUpdated
{
    use Dispatchable;

    /**
     * @param  list<string>  $keys  the setting keys that were saved
     */
    public function __construct(public array $keys) {}
}
