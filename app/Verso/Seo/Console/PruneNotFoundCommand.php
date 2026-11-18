<?php

namespace App\Verso\Seo\Console;

use App\Verso\Seo\NotFound\NotFoundMonitor;
use Illuminate\Console\Command;

// php artisan verso:seo-prune-404: deletes 404 addresses that were not asked for during the retention time.
class PruneNotFoundCommand extends Command
{
    protected $signature = 'verso:seo-prune-404';

    protected $description = 'Delete old rows of the 404 monitor';

    public function handle(NotFoundMonitor $monitor): int
    {
        $this->components->info('Deleted '.$monitor->prune().' old 404 addresses.');

        return self::SUCCESS;
    }
}
