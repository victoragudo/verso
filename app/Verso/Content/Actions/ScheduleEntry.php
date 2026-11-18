<?php

namespace App\Verso\Content\Actions;

use App\Verso\Content\Events\EntrySaved;
use App\Verso\Content\Models\Entry;
use Carbon\CarbonInterface;

// Plans an entry to go live at a future date.
class ScheduleEntry
{
    public function handle(Entry $entry, CarbonInterface $at): Entry
    {
        $entry->schedule($at);

        EntrySaved::dispatch($entry);

        return $entry;
    }
}
