<?php

namespace App\Verso\Easy\Starters\Core;

use App\Verso\Easy\Starters\StarterSite;

// One starter site that comes with Verso.
interface CoreStarter
{
    public function site(): StarterSite;
}
