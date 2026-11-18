<?php

namespace App\Verso\Plugins\Filters;

use App\Verso\Content\Models\Entry;
use App\Verso\Plugins\Contracts\Filter;
use App\Verso\Plugins\FilterRegistry;

// Changes the HTML of one rendered block (top level blocks and plugin child blocks).
// Callbacks: fn (string $html, array $block, ?Entry $entry): string.
final class BlockHtmlFilter implements Filter
{
    /**
     * @param  array<mixed>  $block  the raw block: id, type, attrs, content, children
     */
    public static function apply(string $html, array $block, ?Entry $entry): string
    {
        return (string) app(FilterRegistry::class)->apply(self::class, $html, $block, $entry);
    }
}
