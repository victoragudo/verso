<?php

namespace App\Verso\Content\Admin;

use App\Verso\Content\ContentType;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Content\TaxonomyRegistry;
use App\Verso\Core\Admin\ListColumn;
use App\Verso\Media\Models\Media;
use App\Verso\Media\ResponsiveImages;
use App\Verso\Plugins\Filters\AdminEntryColumnsFilter;
use App\Verso\Site\Seo\EntrySeo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

// The optional columns of the admin entries table and their values, loaded without one query per row.
class EntryListColumns
{
    public function __construct(
        protected TaxonomyRegistry $taxonomies,
        protected ResponsiveImages $images,
        protected ContentLabels $labels,
    ) {}

    /**
     * Columns for one content type. Plugins add more with the AdminEntryColumnsFilter.
     *
     * @return list<ListColumn>
     */
    public function for(ContentType $type): array
    {
        $columns = [new ListColumn('featured_image', __('Image'), 'image', 'xl')];

        $first = true;
        foreach ($this->taxonomies->forContentType($type->name) as $name => $taxonomy) {
            $columns[] = new ListColumn('tax.'.$name, $this->labels->taxonomyPluralLabel($taxonomy), 'tags', $first ? 'lg' : 'xl');
            $first = false;
        }

        array_push(
            $columns,
            new ListColumn('word_count', __('Words'), 'number', '2xl'),
            new ListColumn('updated_at', __('Last modified'), 'date', 'xl'),
            new ListColumn('seo', __('SEO'), 'seo', '3xl'),
        );

        return AdminEntryColumnsFilter::apply($columns, $type);
    }

    /**
     * Adds what the columns need to the list query: terms, and the relations and counts of module and plugin columns.
     *
     * @param  Builder<Entry>  $query
     * @return Builder<Entry>
     */
    public function eagerLoad(Builder $query, ContentType $type): Builder
    {
        $columns = $this->for($type);
        $relations = array_values(array_unique(array_merge(...array_map(fn (ListColumn $column): array => $column->with, $columns))));
        $counts = array_values(array_unique(array_merge(...array_map(fn (ListColumn $column): array => $column->withCount, $columns))));

        return $query
            ->when($relations !== [], fn (Builder $query) => $query->with($relations))
            ->when($counts !== [], fn (Builder $query) => $query->withCount($counts))
            ->when($this->taxonomies->forContentType($type->name) !== [], fn (Builder $query) => $query->with('terms:terms.id,terms.taxonomy,terms.name'));
    }

    /**
     * Loads the featured images of a page of entries with one query. The query runs for every
     * non empty page, so the number of queries of the list never depends on the data.
     *
     * @param  list<Entry>  $entries
     */
    public function preload(array $entries): void
    {
        if ($entries === []) {
            return;
        }

        $ids = [];
        foreach ($entries as $entry) {
            $id = $this->featuredId($entry);

            if ($id !== null) {
                $ids[] = $id;
            }
        }

        $this->images->remember(Media::query()->whereKey($ids)->get()->keyBy('id')->all(), $ids);
    }

    /**
     * Values of the core columns for one row.
     *
     * @return array<string, mixed>
     */
    public function values(Entry $entry): array
    {
        $values = [
            'featured_image' => $this->thumbnail($entry),
            'word_count' => $entry->wordCount(),
            'updated_at' => $entry->updated_at?->toIso8601String(),
            'seo' => $this->seoStatus($entry),
        ];

        if ($entry->relationLoaded('terms')) {
            /** @var Collection<int, Term> $terms */
            $terms = $entry->getRelation('terms');

            foreach ($this->taxonomies->forContentType($entry->type) as $name => $taxonomy) {
                $values['tax.'.$name] = $terms->where('taxonomy', $name)->pluck('name')->values()->all();
            }
        }

        return $values;
    }

    // The traffic light of the editor checks ("good", "ok" or "poor") once the entry was checked.
    // Before that: "complete" with an SEO title and description, "partial" with one of them, "missing" with none.
    public function seoStatus(Entry $entry): string
    {
        $seo = EntrySeo::fromEntry($entry);

        if ($seo->rating() !== null) {
            return $seo->rating();
        }
        $filled = (int) ($seo->title !== null && $seo->title !== '') + (int) ($seo->description !== null && $seo->description !== '');

        return match ($filled) {
            2 => 'complete',
            1 => 'partial',
            default => 'missing',
        };
    }

    /**
     * A small square copy of the featured image, or null.
     *
     * @return array{src: string, alt: string}|null
     */
    protected function thumbnail(Entry $entry): ?array
    {
        $media = $this->images->media($this->featuredId($entry));

        if ($media === null || ! $media->isImage()) {
            return null;
        }

        $size = max(16, (int) config('verso.admin.lists.thumbnail_size'));
        $featured = (array) ($entry->meta['featured_image'] ?? []);
        $alt = is_string($featured['alt'] ?? null) ? $featured['alt'] : (string) ($media->alt ?? '');

        return ['src' => $media->imageUrl(['w' => $size, 'h' => $size]), 'alt' => $alt];
    }

    protected function featuredId(Entry $entry): ?int
    {
        $id = data_get($entry->meta ?? [], 'featured_image.mediaId');

        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }
}
