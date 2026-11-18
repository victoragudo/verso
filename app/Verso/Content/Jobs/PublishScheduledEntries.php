<?php

namespace App\Verso\Content\Jobs;

use App\Verso\Content\Actions\PublishEntry;
use App\Verso\Content\Models\Entry;
use Illuminate\Foundation\Bus\Dispatchable;

// Publishes every scheduled entry whose date has come. Runs every minute.
class PublishScheduledEntries
{
    use Dispatchable;

    public function handle(PublishEntry $publishEntry): int
    {
        $published = 0;

        Entry::query()->dueForPublishing()->orderBy('published_at')->each(function (Entry $entry) use ($publishEntry, &$published): void {
            $publishEntry->handle($entry, $entry->published_at);
            $published++;
        });

        return $published;
    }
}
