<?php

namespace App\Verso\Seo\Audit\Checks;

use App\Verso\Seo\Audit\IssueFix;
use App\Verso\Seo\Audit\SeoCheck;

// Shared defaults of the core checks.
abstract class BaseCheck implements SeoCheck
{
    public function fix(): ?IssueFix
    {
        return null;
    }
}
