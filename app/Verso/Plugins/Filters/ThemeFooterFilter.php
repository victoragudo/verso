<?php

namespace App\Verso\Plugins\Filters;

use App\Verso\Plugins\Contracts\Filter;
use App\Verso\Plugins\FilterRegistry;
use Illuminate\Http\Request;

// Adds HTML at the end of every public page, before </body>. Callbacks: fn (string $html, Request $request): string.
final class ThemeFooterFilter implements Filter
{
    public static function apply(string $html, Request $request): string
    {
        return (string) app(FilterRegistry::class)->apply(self::class, $html, $request);
    }
}
