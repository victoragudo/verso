<?php

namespace App\Verso\Theme\Support;

use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use App\Verso\Media\ResponsiveImages;
use App\Verso\Plugins\Filters\EntryQueryFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

// Paginates published entries, newest first, with the "posts per page" setting.
class EntryListing
{
    public function __construct(protected Settings $settings, protected ResponsiveImages $images) {}

    /**
     * A page of published entries. Plugins can change the query with the EntryQueryFilter.
     * Asking for a page after the last one gives a 404.
     *
     * @param  Builder<Entry>  $query
     * @return LengthAwarePaginator<int, Entry>
     */
    public function paginate(Builder $query, ArchiveContext $context): LengthAwarePaginator
    {
        $entries = EntryQueryFilter::apply($query->published(), $context)
            ->with('author')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($this->perPage())
            ->withQueryString();

        abort_if($entries->currentPage() > 1 && $entries->isEmpty(), 404);

        $this->images->preloadEntries($entries->items());

        return $entries;
    }

    public function perPage(): int
    {
        $perPage = $this->settings->get('reading.posts_per_page');

        return max(1, is_numeric($perPage) ? (int) $perPage : 10);
    }
}
