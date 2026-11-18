<?php

namespace App\Verso\Content\Actions;

use App\Verso\Content\Events\EntryPublished;
use App\Verso\Content\Events\EntrySaved;
use App\Verso\Content\Models\Entry;
use Carbon\CarbonInterface;

// Makes an entry live now (or at a past date).
class PublishEntry
{
    public function handle(Entry $entry, ?CarbonInterface $at = null): Entry
    {
        $entry->publish($at);

        EntryPublished::dispatch($entry);
        EntrySaved::dispatch($entry);

        return $entry;
    }
}
