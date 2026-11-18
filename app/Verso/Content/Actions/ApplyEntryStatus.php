<?php

namespace App\Verso\Content\Actions;

use App\Verso\Content\Enums\EntryStatus;
use App\Verso\Content\Models\Entry;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

// Moves an entry to the status picked in the editor. Returns true when it was published now.
class ApplyEntryStatus
{
    public function handle(Entry $entry, EntryStatus $status, ?CarbonInterface $at = null): bool
    {
        $wasPublished = $entry->isPublished();

        match ($status) {
            EntryStatus::Published => $at !== null && $at->isFuture()
                ? $entry->schedule($at)
                : $entry->publish($at),
            EntryStatus::Scheduled => $entry->schedule($at ?? throw new InvalidArgumentException('A scheduled entry needs a date.')),
            EntryStatus::Draft => $this->draft($entry, $at),
            EntryStatus::Trash => $entry->moveToTrash(),
        };

        return ! $wasPublished && $entry->isPublished();
    }

    // Drafts keep the wanted publish date, if any.
    protected function draft(Entry $entry, ?CarbonInterface $at): void
    {
        $entry->published_at = $at === null ? null : Carbon::instance($at);
        $entry->unpublish();
    }
}
