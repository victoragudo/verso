<?php

namespace App\Verso\Site\Listeners;

use App\Verso\Content\Events\EntryPublished;
use App\Verso\Site\Jobs\PingIndexNow;
use App\Verso\Site\Seo\SeoSettings;

// Queues an IndexNow ping when an entry goes live and IndexNow is on.
class NotifyIndexNow
{
    public function __construct(protected SeoSettings $seo) {}

    public function handle(EntryPublished $event): void
    {
        if ($this->seo->indexNowKey() === null || $this->seo->discourage() || ! $event->entry->isPublished()) {
            return;
        }

        PingIndexNow::dispatch([$event->entry->url()]);
    }
}
