<?php

namespace App\Verso\Content\Actions;

use App\Verso\Content\Events\EntrySaved;
use App\Verso\Content\Models\Entry;

// Brings an entry back from the trash as a draft.
class RestoreEntry
{
    public function handle(Entry $entry): Entry
    {
        if (! $entry->trashed()) {
            return $entry;
        }

        $entry->restoreFromTrash();

        EntrySaved::dispatch($entry);

        return $entry;
    }
}
