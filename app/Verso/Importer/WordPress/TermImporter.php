<?php

namespace App\Verso\Importer\WordPress;

use App\Verso\Content\Actions\CreateTerm;
use App\Verso\Content\Actions\UpdateTerm;
use App\Verso\Content\Models\Term;
use App\Verso\Content\Support\SlugGenerator;
use App\Verso\Content\TaxonomyRegistry;
use App\Verso\Importer\Support\ImportReport;
use App\Verso\Importer\Wxr\WxrItem;
use App\Verso\Importer\Wxr\WxrTerm;
use InvalidArgumentException;

// Finds or creates Verso terms for WordPress categories and tags.
class TermImporter
{
    // WordPress taxonomy names that have another name in Verso.
    protected const TAXONOMY_NAMES = ['post_tag' => 'tag'];

    public function __construct(
        protected TaxonomyRegistry $taxonomies,
        protected SlugGenerator $slugs,
        protected CreateTerm $createTerm,
        protected UpdateTerm $updateTerm,
    ) {}

    // The Verso taxonomy for a WordPress taxonomy, or null when Verso does not know it.
    public function taxonomyFor(string $wordpressTaxonomy): ?string
    {
        $name = self::TAXONOMY_NAMES[$wordpressTaxonomy] ?? $wordpressTaxonomy;

        return $this->taxonomies->has($name) ? $name : null;
    }

    public function import(WxrTerm $term, ImportState $state): ?Term
    {
        $taxonomy = $this->taxonomyFor($term->taxonomy);

        if ($taxonomy === null) {
            $state->report->add('terms', ImportReport::SKIPPED);

            return null;
        }

        $slug = $this->slugs->slugify($term->slug !== '' ? $term->slug : $term->name);
        $key = $taxonomy.':'.$slug;

        if (isset($state->terms[$key])) {
            return Term::query()->find($state->terms[$key]);
        }

        $existing = Term::query()->inTaxonomy($taxonomy)->where('slug', $slug)->first();

        if ($existing !== null) {
            $state->report->add('terms', ImportReport::SKIPPED);
        } else {
            $existing = $this->createTerm->handle($taxonomy, [
                'name' => $term->name !== '' ? $term->name : $term->slug,
                'slug' => $slug,
                'description' => $term->description !== '' ? $term->description : null,
            ]);
            $state->report->add('terms', ImportReport::CREATED);
        }

        $state->terms[$key] = $existing->id;

        if ($term->parentSlug !== '') {
            $state->pendingTermParents[] = ['term' => $existing->id, 'taxonomy' => $taxonomy, 'parent' => $term->parentSlug];
        }

        return $existing;
    }

    /**
     * Term ids for an item, grouped by taxonomy. Only taxonomies of the content type are used.
     *
     * @param  list<string>  $allowedTaxonomies
     * @return array<string, list<int>>
     */
    public function idsFor(WxrItem $item, array $allowedTaxonomies, ImportState $state): array
    {
        $ids = array_fill_keys($allowedTaxonomies, []);

        foreach ($item->terms as $wxrTerm) {
            $taxonomy = $this->taxonomyFor($wxrTerm->taxonomy);

            if ($taxonomy === null || ! in_array($taxonomy, $allowedTaxonomies, true)) {
                continue;
            }

            $term = $this->import($wxrTerm, $state);

            if ($term !== null) {
                $ids[$taxonomy][] = $term->id;
            }
        }

        return $ids;
    }

    // Set parents once every term exists, because a child can come before its parent.
    public function resolveParents(ImportState $state): void
    {
        foreach ($state->pendingTermParents as $pending) {
            $term = Term::query()->find($pending['term']);
            $parentId = $state->terms[$pending['taxonomy'].':'.$this->slugs->slugify($pending['parent'])] ?? null;

            if ($term === null || $parentId === null || $term->parent_id === $parentId) {
                continue;
            }

            try {
                $this->updateTerm->handle($term, ['parent_id' => $parentId]);
            } catch (InvalidArgumentException) {
                $state->report->warn(__('The term ":name" cannot have that parent, so it was left at the top level.', ['name' => $term->name]));
            }
        }

        $state->pendingTermParents = [];
    }
}
