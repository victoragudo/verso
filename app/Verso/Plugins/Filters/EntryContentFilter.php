<?php

namespace App\Verso\Plugins\Filters;

use App\Verso\Content\Models\Entry;
use App\Verso\Plugins\Contracts\Filter;
use App\Verso\Plugins\FilterRegistry;

// Changes the rendered HTML of an entry. Callbacks: fn (string $html, Entry $entry): string.
final class EntryContentFilter implements Filter
{
    public static function apply(string $html, Entry $entry): string
    {
        return (string) app(FilterRegistry::class)->apply(self::class, $html, $entry);
    }
}
