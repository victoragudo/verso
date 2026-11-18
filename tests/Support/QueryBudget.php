<?php

namespace Tests\Support;

use Closure;
use Illuminate\Support\Facades\DB;

// Counts database queries and keeps the most queries each key page may run.
class QueryBudget
{
    // Most queries per page with the page cache off. Lists must not grow with the number of entries.
    // The demo theme also loads post categories and the category strip on the home, archive and term pages,
    // and the previous and next posts and related posts on an entry page.
    public const BUDGETS = [
        'home' => 6,
        'entry' => 8,
        'page' => 8,
        'archive' => 6,
        'term' => 7,
        'feed' => 4,
        'sitemap' => 2,
        'admin.entries' => 6,
        'admin.dashboard' => 12,
    ];

    // Most queries for a page served from the page cache.
    public const CACHED_PAGE = 2;

    public static function for(string $page): int
    {
        return self::BUDGETS[$page];
    }

    // Runs the callback and returns how many queries it ran.
    public static function count(Closure $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $callback();

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }
}
