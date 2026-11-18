<?php

namespace App\Verso\Content\Actions;

use App\Verso\Content\Events\EntryDeleted;
use App\Verso\Content\Models\Entry;

// Moves an entry to the trash.
class TrashEntry
{
    public function handle(Entry $entry): Entry
    {
        if ($entry->trashed()) {
            return $entry;
        }

        $entry->moveToTrash();

        EntryDeleted::dispatch($entry);

        return $entry;
    }
}
