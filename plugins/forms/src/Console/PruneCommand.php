<?php

namespace Verso\Plugins\Forms\Console;

use Illuminate\Console\Command;
use Verso\Plugins\Forms\Actions\PruneSubmissions;

// php artisan forms:prune deletes submissions older than the retention period.
class PruneCommand extends Command
{
    protected $signature = 'forms:prune';

    protected $description = 'Delete form submissions older than the retention period';

    public function handle(PruneSubmissions $prune): int
    {
        $this->info("Deleted {$prune->handle()} old submissions.");

        return self::SUCCESS;
    }
}
