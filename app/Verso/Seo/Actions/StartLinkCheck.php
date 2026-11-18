<?php

namespace App\Verso\Seo\Actions;

use App\Verso\Content\Models\Entry;
use App\Verso\Seo\Jobs\CheckLinks;
use App\Verso\Seo\Links\CrawlState;

// Starts the link check of all published content in the background. Only one check runs at a time.
class StartLinkCheck
{
    public function __construct(protected CrawlState $state) {}

    public function handle(): bool
    {
        if (! $this->state->start(Entry::query()->published()->count())) {
            return false;
        }

        CheckLinks::dispatch(0);

        return true;
    }
}
