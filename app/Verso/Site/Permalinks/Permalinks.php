<?php

namespace App\Verso\Site\Permalinks;

use App\Verso\Content\ContentType;
use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Content\Taxonomy;
use App\Verso\Content\TaxonomyRegistry;
use App\Verso\Core\Support\SiteTime;

// Builds the public paths of entries, terms and archives from the permalink settings.
class Permalinks
{
    public function __construct(
        protected PermalinkSettings $settings,
        protected ContentTypeRegistry $types,
        protected TaxonomyRegistry $taxonomies,
        protected SiteTime $time,
        protected string $categoryTaxonomy,
        protected string $uncategorized,
    ) {}

    public function settings(): PermalinkSettings
    {
        return $this->settings;
    }

    public function entryPath(Entry $entry): string
    {
        if ($entry->isFrontPage()) {
            return '/';
        }

        return $this->finish($this->settings->pattern($entry->contentType())->build($this->values($entry)));
    }

    public function termPath(Term $term): string
    {
        $taxonomy = $this->taxonomies->get($term->taxonomy);

        return $this->finish('/'.ltrim($this->settings->taxonomyBase($taxonomy).'/'.$term->slug, '/'));
    }

    // The archive path, like "/blog", or null when the type has none.
    public function archivePath(ContentType|string $type): ?string
    {
        $type = is_string($type) ? $this->types->find($type) : $type;
        $base = $type === null ? '' : $this->settings->archiveBase($type);

        return $base === '' ? null : $this->finish('/'.$base);
    }

    public function archiveUrl(string $type): ?string
    {
        $path = $this->archivePath($type);

        return $path === null ? null : url($path);
    }

    public function taxonomyBase(Taxonomy $taxonomy): string
    {
        return $this->settings->taxonomyBase($taxonomy);
    }

    // Adds the trailing slash when it is on. Paths that look like files (with a dot) never get one.
    public function finish(string $path): string
    {
        $path = '/'.trim($path, '/');

        if ($path === '/' || ! $this->settings->trailingSlash() || str_contains(basename($path), '.')) {
            return $path;
        }

        return $path.'/';
    }

    /**
     * Token values of an entry. The date uses the site time zone. Only tokens of the
     * current pattern are filled, unless $all is true (the admin preview needs every one).
     *
     * @return array<string, string>
     */
    public function values(Entry $entry, bool $all = false): array
    {
        $pattern = $this->settings->pattern($entry->contentType());
        $values = ['slug' => $entry->slugPath(), 'id' => (string) $entry->id];

        if ($all || $pattern->has('year') || $pattern->has('month') || $pattern->has('day')) {
            $date = $this->time->local($entry->published_at ?? $entry->created_at ?? now());
            $values += ['year' => $date->format('Y'), 'month' => $date->format('m'), 'day' => $date->format('d')];
        }

        if ($all || $pattern->has('category')) {
            $values['category'] = $this->categorySlug($entry);
        }

        return $values;
    }

    // The first category of the entry (lowest id), or the "uncategorized" fallback.
    protected function categorySlug(Entry $entry): string
    {
        $terms = $entry->relationLoaded('terms')
            ? $entry->terms->where('taxonomy', $this->categoryTaxonomy)->sortBy('id')->first()?->slug
            : $entry->terms()->where('taxonomy', $this->categoryTaxonomy)->orderBy('terms.id')->value('slug');

        return is_string($terms) && $terms !== '' ? $terms : $this->uncategorized;
    }
}
