<?php

namespace App\Verso\Site\Listeners;

use App\Verso\Content\Models\Entry;
use App\Verso\Site\Seo\EntrySeo;

// Cleans entries.meta.seo before an entry is saved, so only safe values are stored.
class NormalizeEntrySeo
{
    public function handle(Entry $entry): void
    {
        $meta = $entry->meta ?? [];

        if (! array_key_exists(EntrySeo::META_KEY, $meta)) {
            return;
        }

        $seo = $meta[EntrySeo::META_KEY];
        $meta[EntrySeo::META_KEY] = EntrySeo::fromArray(is_array($seo) ? $seo : [])->toArray();
        $entry->meta = $meta;
    }
}
