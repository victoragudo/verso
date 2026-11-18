<?php

namespace App\Verso\Plugins\Filters;

use App\Models\User;
use App\Verso\Plugins\Contracts\Filter;
use App\Verso\Plugins\FilterRegistry;
use App\Verso\Seo\AltText\AltTextSuggester;

// Offers alt text suggestions on the bulk "Add alt text" screen of the SEO overview.
// Callbacks: fn (?AltTextSuggester $suggester, User $user): ?AltTextSuggester. Return null when the user may not use it.
final class AltTextSuggesterFilter implements Filter
{
    public static function apply(?AltTextSuggester $suggester, User $user): ?AltTextSuggester
    {
        $result = app(FilterRegistry::class)->apply(self::class, $suggester, $user);

        return $result instanceof AltTextSuggester ? $result : null;
    }
}
