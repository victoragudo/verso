<?php

namespace Verso\Plugins\Analytics\Jobs;

use Illuminate\Foundation\Bus\Dispatchable;
use Verso\Plugins\Analytics\Actions\PruneAnalytics;
use Verso\Plugins\Analytics\Actions\RollupPendingDays;

// Daily job: roll up past days first, then delete old raw events and old salts.
class RollupAnalytics
{
    use Dispatchable;

    public function handle(RollupPendingDays $rollup, PruneAnalytics $prune): void
    {
        $rollup->handle();
        $prune->handle();
    }
}
