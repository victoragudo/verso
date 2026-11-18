<?php

namespace App\Verso\Theme\Support;

use App\Verso\Content\Models\Entry;

// Finds the published entry behind a public URL path, like "about/team".
class EntryResolver
{
    /**
     * Walk the slugs from the top parent down. Every entry on the way must be published.
     *
     * @param  list<string>  $types
     */
    public function resolve(array $types, string $slugPath): ?Entry
    {
        $slugs = array_values(array_filter(explode('/', trim($slugPath, '/')), fn (string $slug): bool => $slug !== ''));

        if ($slugs === [] || $types === []) {
            return null;
        }

        $entry = null;

        foreach ($slugs as $slug) {
            $entry = Entry::query()
                ->whereIn('type', $entry === null ? $types : [$entry->type])
                ->where('slug', $slug)
                ->where('parent_id', $entry?->id)
                ->published()
                ->with('parent')
                ->first();

            if ($entry === null) {
                return null;
            }
        }

        return $entry;
    }
}
