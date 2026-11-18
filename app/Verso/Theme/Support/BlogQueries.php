<?php

namespace App\Verso\Theme\Support;

use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Media\ResponsiveImages;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

// Small read-only queries that blog themes use around a post: neighbours, related posts and the category strip.
class BlogQueries
{
    public function __construct(protected ResponsiveImages $images) {}

    /**
     * The published entries of the same type just before and just after this one, by date, in one query.
     *
     * @return array{previous: Entry|null, next: Entry|null}
     */
    public function neighbours(Entry $entry): array
    {
        if ($entry->published_at === null) {
            return ['previous' => null, 'next' => null];
        }

        $found = $this->neighbour($entry, '<', 'desc')
            ->unionAll($this->neighbour($entry, '>', 'asc'))
            ->get();

        return [
            'previous' => $found->first(fn (Entry $item): bool => $this->isBefore($item, $entry)),
            'next' => $found->first(fn (Entry $item): bool => ! $this->isBefore($item, $entry)),
        ];
    }

    /**
     * Newest published entries that share a category with this one. Their featured images are loaded in one query.
     *
     * @return Collection<int, Entry>
     */
    public function related(Entry $entry, string $taxonomy, int $limit): Collection
    {
        $termIds = $entry->termsIn($taxonomy)->pluck('id')->all();

        if ($termIds === [] || $limit < 1) {
            return new Collection;
        }

        $related = Entry::query()
            ->ofType($entry->type)
            ->published()
            ->whereKeyNot($entry->id)
            ->whereHas('terms', fn (Builder $query) => $query->whereIn('terms.id', $termIds))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $avatar = $entry->author?->avatar_media_id;
        $this->images->preloadEntries($related->all(), $avatar === null ? [] : [(int) $avatar]);

        return $related;
    }

    /**
     * Terms of a taxonomy with the number of published entries, biggest first. Empty terms are left out.
     *
     * @return Collection<int, Term>
     */
    public function popularTerms(string $taxonomy, int $limit): Collection
    {
        return Term::query()
            ->inTaxonomy($taxonomy)
            ->withCount(['entries' => $this->onlyPublished(...)])
            ->orderByDesc('entries_count')
            ->orderBy('name')
            ->orderBy('id')
            ->limit(max(0, $limit))
            ->get()
            ->filter(fn (Term $term): bool => (int) $term->getAttribute('entries_count') > 0)
            ->values();
    }

    /**
     * @param  Builder<Entry>  $query
     * @return Builder<Entry>
     */
    protected function onlyPublished(Builder $query): Builder
    {
        return $query->published();
    }

    /**
     * @param  '<'|'>'  $operator
     * @param  'asc'|'desc'  $direction
     * @return Builder<Entry>
     */
    protected function neighbour(Entry $entry, string $operator, string $direction): Builder
    {
        return Entry::query()
            ->select(['id', 'type', 'title', 'slug', 'parent_id', 'status', 'published_at'])
            ->ofType($entry->type)
            ->published()
            ->whereKeyNot($entry->id)
            ->where(fn (Builder $query) => $query
                ->where('published_at', $operator, $entry->published_at)
                ->orWhere(fn (Builder $same) => $same->where('published_at', $entry->published_at)->where('id', $operator, $entry->id)))
            ->orderBy('published_at', $direction)
            ->orderBy('id', $direction)
            ->limit(1);
    }

    protected function isBefore(Entry $item, Entry $entry): bool
    {
        return $item->published_at < $entry->published_at
            || ($item->published_at == $entry->published_at && $item->id < $entry->id);
    }
}
