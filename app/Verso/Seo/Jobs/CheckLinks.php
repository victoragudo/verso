<?php

namespace App\Verso\Seo\Jobs;

use App\Verso\Content\Models\Entry;
use App\Verso\Seo\Audit\SeoAudit;
use App\Verso\Seo\Links\CrawlState;
use App\Verso\Seo\Links\LinkChecker;
use App\Verso\Seo\Models\PageReport;
use App\Verso\Seo\Models\SeoLink;
use App\Verso\System\Server\RuntimeLimits;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

// Checks the links of one batch of published entries, then queues the next batch until all are done.
class CheckLinks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public int $afterId = 0)
    {
        $this->onQueue((string) config('verso.seo.crawl.queue'));
    }

    public function handle(LinkChecker $checker, CrawlState $state, RuntimeLimits $limits, SeoAudit $audit): void
    {
        $limits->raiseForHeavyTask();
        $batchSize = max(1, (int) config('verso.seo.crawl.batch_size'));

        $entries = Entry::query()->published()
            ->where('id', '>', $this->afterId)
            ->orderBy('id')
            ->limit($batchSize)
            ->get();

        foreach ($entries as $entry) {
            $checker->check($entry);
        }

        $state->advance($entries->count());

        if ($entries->count() === $batchSize) {
            self::dispatch((int) $entries->last()?->id);

            return;
        }

        $this->forgetUnpublished();
        $state->finish();
        $audit->flush();
    }

    // Results of entries that are no longer published are dropped.
    protected function forgetUnpublished(): void
    {
        $published = Entry::query()->published()->select('id');

        PageReport::query()->whereNotIn('entry_id', $published)->delete();
        SeoLink::query()->whereNotIn('source_entry_id', $published)->delete();
    }
}
