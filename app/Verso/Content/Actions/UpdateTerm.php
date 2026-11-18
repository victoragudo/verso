<?php

namespace App\Verso\Content\Actions;

use App\Verso\Content\Models\Term;
use App\Verso\Content\Support\SlugGenerator;
use App\Verso\Core\Models\Redirect;
use InvalidArgumentException;

/**
 * Changes a term. A new slug adds a redirect from the old term URL.
 *
 * @phpstan-import-type TermData from CreateTerm
 */
class UpdateTerm
{
    public function __construct(protected SlugGenerator $slugs) {}

    /**
     * @param  TermData  $data
     */
    public function handle(Term $term, array $data): Term
    {
        $oldPath = $term->path();

        $term->fill(array_intersect_key($data, array_flip(['name', 'description'])));

        if (array_key_exists('parent_id', $data) && $data['parent_id'] !== $term->parent_id) {
            $parent = $data['parent_id'] === null ? null : Term::query()->find($data['parent_id']);

            if (! $term->canHaveParent($parent)) {
                throw new InvalidArgumentException('This term cannot have that parent.');
            }

            $term->parent_id = $parent?->id;
        }

        if (array_key_exists('slug', $data) && $data['slug'] !== $term->slug) {
            $term->slug = $this->slugs->forTerm($term->taxonomy, ($data['slug'] ?? '') ?: $term->name, $term->id);
        }

        $term->save();

        if ($term->path() !== $oldPath) {
            Redirect::point($oldPath, $term->path());
        }

        return $term;
    }
}
