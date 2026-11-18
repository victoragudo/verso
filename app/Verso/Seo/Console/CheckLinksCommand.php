<?php

namespace App\Verso\Seo\Console;

use App\Verso\Seo\Actions\StartLinkCheck;
use App\Verso\Seo\Links\CrawlState;
use Illuminate\Console\Command;

// php artisan verso:seo-check-links: checks the links of all published content in the background.
class CheckLinksCommand extends Command
{
    protected $signature = 'verso:seo-check-links';

    protected $description = 'Check internal links, orphan pages and slow pages of all published content';

    public function handle(StartLinkCheck $start, CrawlState $state): int
    {
        if (! $start->handle()) {
            $this->components->warn('A link check is already running.');

            return self::SUCCESS;
        }

        $current = $state->current();
        $this->components->info($current['status'] === CrawlState::DONE
            ? "Checked {$current['checked']} pages."
            : "The link check of {$current['total']} pages has started.");

        return self::SUCCESS;
    }
}
