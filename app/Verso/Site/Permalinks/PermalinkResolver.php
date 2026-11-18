<?php

namespace App\Verso\Site\Permalinks;

use App\Verso\Content\ContentType;
use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Content\TaxonomyRegistry;
use App\Verso\Theme\Support\EntryResolver;

// Finds what a public path shows. Old structures and wrong dates or categories get a redirect to the right path.
class PermalinkResolver
{
    public function __construct(
        protected Permalinks $permalinks,
        protected ContentTypeRegistry $types,
        protected TaxonomyRegistry $taxonomies,
        protected EntryResolver $entries,
    ) {}

    public function resolve(string $path): ?ResolvedPath
    {
        $path = trim($path, '/');

        if ($this->permalinks->settings()->lowercase() && $path !== mb_strtolower($path)) {
            return ResolvedPath::redirect($this->permalinks->finish(mb_strtolower($path)));
        }

        return $this->archive($path)
            ?? $this->term($path)
            ?? $this->entry($path)
            ?? $this->previous($path);
    }

    /** @return list<ContentType> types with the most fixed parts first */
    public function typesInMatchOrder(): array
    {
        $settings = $this->permalinks->settings();
        $types = array_values($this->types->all());

        usort($types, fn (ContentType $a, ContentType $b): int => $settings->pattern($b)->specificity() <=> $settings->pattern($a)->specificity()
            ?: (int) $a->hierarchical <=> (int) $b->hierarchical);

        return $types;
    }

    protected function archive(string $path): ?ResolvedPath
    {
        foreach ($this->types->all() as $type) {
            $base = $this->permalinks->settings()->archiveBase($type);

            if ($base !== '' && $base === $path) {
                return ResolvedPath::archive($type);
            }
        }

        return null;
    }

    protected function term(string $path): ?ResolvedPath
    {
        foreach ($this->taxonomies->all() as $taxonomy) {
            $slug = $this->slugAfter($this->permalinks->taxonomyBase($taxonomy), $path);
            $term = $slug === null ? null : Term::query()->inTaxonomy($taxonomy->name)->where('slug', $slug)->first();

            if ($term !== null) {
                return ResolvedPath::term($taxonomy, $term);
            }
        }

        return null;
    }

    protected function entry(string $path): ?ResolvedPath
    {
        foreach ($this->typesInMatchOrder() as $type) {
            $entry = $this->find($type, $this->permalinks->settings()->pattern($type), $path);

            if ($entry === null) {
                continue;
            }

            // The front page lives at "/" only. A wrong date or category points to the right path.
            $canonical = $this->permalinks->entryPath($entry);

            return trim($canonical, '/') === $path ? ResolvedPath::entry($entry) : ResolvedPath::redirect($canonical);
        }

        return null;
    }

    // Paths from older URL structures, bases and archive names send visitors to the new path.
    protected function previous(string $path): ?ResolvedPath
    {
        $settings = $this->permalinks->settings();

        foreach ($this->types->all() as $type) {
            if (in_array($path, $settings->previousArchiveBases($type), true) && ($archive = $this->permalinks->archivePath($type)) !== null) {
                return ResolvedPath::redirect($archive);
            }

            foreach ($settings->previousPatterns($type) as $pattern) {
                $entry = $this->find($type, $pattern, $path);

                if ($entry !== null) {
                    return ResolvedPath::redirect($this->permalinks->entryPath($entry));
                }
            }
        }

        foreach ($this->taxonomies->all() as $taxonomy) {
            foreach ($settings->previousTaxonomyBases($taxonomy) as $base) {
                $slug = $this->slugAfter($base, $path);
                $term = $slug === null ? null : Term::query()->inTaxonomy($taxonomy->name)->where('slug', $slug)->first();

                if ($term !== null) {
                    return ResolvedPath::redirect($this->permalinks->termPath($term));
                }
            }
        }

        return null;
    }

    // The published entry of a type behind a path that fits the pattern.
    protected function find(ContentType $type, PermalinkPattern $pattern, string $path): ?Entry
    {
        $values = $pattern->match($path, $type->hierarchical);

        if ($values === null) {
            return null;
        }

        if (isset($values['id'])) {
            $entry = Entry::query()->ofType($type->name)->published()->with('parent')->find((int) $values['id']);

            return $entry instanceof Entry ? $entry : null;
        }

        return isset($values['slug']) ? $this->entries->resolve([$type->name], $values['slug']) : null;
    }

    // "travel" for base "category" and path "category/travel". Null when the path has another form.
    protected function slugAfter(string $base, string $path): ?string
    {
        $prefix = $base === '' ? '' : $base.'/';

        if ($base === '' || ! str_starts_with($path, $prefix)) {
            return null;
        }

        $slug = substr($path, strlen($prefix));

        return $slug === '' || str_contains($slug, '/') ? null : $slug;
    }
}
