<?php

namespace App\Verso\Content\Actions;

use App\Verso\Content\Models\Term;
use App\Verso\Content\Support\SlugGenerator;
use App\Verso\Content\TaxonomyRegistry;
use InvalidArgumentException;

/**
 * Creates a term in a taxonomy.
 *
 * @phpstan-type TermData array{name?: string, slug?: string|null, parent_id?: int|null, description?: string|null}
 */
class CreateTerm
{
    public function __construct(
        protected TaxonomyRegistry $taxonomies,
        protected SlugGenerator $slugs,
    ) {}

    /**
     * @param  TermData  $data
     */
    public function handle(string $taxonomy, array $data): Term
    {
        $this->taxonomies->get($taxonomy);

        $term = new Term([
            'taxonomy' => $taxonomy,
            'name' => $data['name'] ?? '',
            'description' => $data['description'] ?? null,
        ]);

        $parent = isset($data['parent_id']) ? Term::query()->find($data['parent_id']) : null;

        if (! $term->canHaveParent($parent)) {
            throw new InvalidArgumentException('This term cannot have that parent.');
        }

        $term->parent_id = $parent?->id;
        $term->slug = $this->slugs->forTerm($taxonomy, ($data['slug'] ?? '') ?: $term->name);
        $term->save();

        return $term;
    }
}
