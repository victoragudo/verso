<?php

namespace App\Verso\Plugins\Filters;

use App\Verso\Content\Models\Entry;
use App\Verso\Plugins\Contracts\Filter;
use App\Verso\Plugins\FilterRegistry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

// Changes the page of public search results, for example to reorder or tag them.
// Callbacks: fn (LengthAwarePaginator $results, string $search): LengthAwarePaginator.
final class SearchResultsFilter implements Filter
{
    /**
     * @param  LengthAwarePaginator<int, Entry>  $results
     * @return LengthAwarePaginator<int, Entry>
     */
    public static function apply(LengthAwarePaginator $results, string $search): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, Entry> */
        return app(FilterRegistry::class)->apply(self::class, $results, $search);
    }
}
