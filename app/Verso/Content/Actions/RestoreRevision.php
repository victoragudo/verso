<?php

namespace App\Verso\Content\Actions;

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Revision;
use InvalidArgumentException;

// Puts the content of an old revision back on the entry. The current content becomes a revision too.
class RestoreRevision
{
    public function __construct(protected UpdateEntry $updateEntry) {}

    public function handle(Entry $entry, Revision $revision, ?User $editor = null): Entry
    {
        if ($revision->entry_id !== $entry->id) {
            throw new InvalidArgumentException('This revision belongs to another entry.');
        }

        return $this->updateEntry->handle($entry, [
            'title' => $revision->title,
            'blocks' => $revision->blocks,
            'excerpt' => $revision->excerpt,
            'meta' => $revision->meta,
        ], $editor);
    }
}
