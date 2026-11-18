<?php

namespace App\Verso\Content\Actions;

use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Content\TaxonomyRegistry;

// Sets the terms of an entry, one taxonomy at a time. Unknown or foreign term ids are ignored.
class SyncEntryTerms
{
    public function __construct(protected TaxonomyRegistry $taxonomies) {}

    /**
     * @param  array<string, list<int|string>|null>  $termsByTaxonomy
     */
    public function handle(Entry $entry, array $termsByTaxonomy): void
    {
        foreach ($this->taxonomies->forContentType($entry->type) as $name => $taxonomy) {
            if (! array_key_exists($name, $termsByTaxonomy)) {
                continue;
            }

            $wanted = Term::query()
                ->inTaxonomy($name)
                ->whereKey(array_map('intval', $termsByTaxonomy[$name] ?? []))
                ->pluck('id');

            $current = $entry->terms()->where('taxonomy', $name)->pluck('terms.id');

            $entry->terms()->detach($current->diff($wanted)->all());
            $entry->terms()->syncWithoutDetaching($wanted->all());
        }

        $entry->unsetRelation('terms');
    }
}
