<?php

namespace App\Verso\Seo\Audit;

use App\Verso\Core\Enums\Capability;

// A check that can fix its issue with one click, like turning the sitemap on.
interface FixableCheck extends SeoCheck
{
    // The extra capability needed to apply the fix (seo.manage is always needed).
    public function fixCapability(): Capability;

    // Applies the fix and returns a short message for the toast.
    public function applyFix(): string;
}
