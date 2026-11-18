<?php

namespace App\Verso\Site\Events;

use Illuminate\Foundation\Events\Dispatchable;

// Fired when what visitors see of a part changes: a publish or a restore of the theme default.
class SitePartPublished
{
    use Dispatchable;

    public function __construct(public string $theme, public string $part) {}
}
