<?php

namespace App\Verso\Plugins\Filters;

use App\Verso\Content\Models\Entry;
use App\Verso\Plugins\Contracts\Filter;
use App\Verso\Plugins\FilterRegistry;
use App\Verso\Theme\Support\ArchiveContext;
use Illuminate\Database\Eloquent\Builder;

// Changes the entry query of public lists (home, archives, terms, search) before it is paginated.
// Callbacks: fn (Builder $query, ArchiveContext $context): Builder.
final class EntryQueryFilter implements Filter
{
    /**
     * @param  Builder<Entry>  $query
     * @return Builder<Entry>
     */
    public static function apply(Builder $query, ArchiveContext $context): Builder
    {
        /** @var Builder<Entry> */
        return app(FilterRegistry::class)->apply(self::class, $query, $context);
    }
}
