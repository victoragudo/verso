<?php

namespace Verso\Plugins\Forms\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Verso\Plugins\Forms\Actions\PruneSubmissions;

// Scheduled job that applies the retention period.
class PruneOldSubmissions implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public function handle(PruneSubmissions $prune): void
    {
        $prune->handle();
    }
}
