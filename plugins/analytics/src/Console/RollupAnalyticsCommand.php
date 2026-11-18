<?php

namespace Verso\Plugins\Analytics\Console;

use Illuminate\Console\Command;
use Verso\Plugins\Analytics\Actions\PruneAnalytics;
use Verso\Plugins\Analytics\Actions\RollupDay;
use Verso\Plugins\Analytics\Actions\RollupPendingDays;

// php artisan analytics:rollup — rolls up past days (or one day) and prunes old data.
class RollupAnalyticsCommand extends Command
{
    protected $signature = 'analytics:rollup {--day= : Roll up this day again (Y-m-d)} {--no-prune : Keep old raw events and salts}';

    protected $description = 'Roll up analytics events into daily totals and delete old raw events';

    public function handle(RollupPendingDays $pending, RollupDay $rollupDay, PruneAnalytics $prune): int
    {
        $day = $this->option('day');

        if (is_string($day) && $day !== '') {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) !== 1) {
                $this->error('The day must look like 2026-11-16.');

                return self::FAILURE;
            }

            $rows = $rollupDay->handle($day);
            $this->info("Rolled up {$day} ({$rows} rows).");
        } else {
            $days = $pending->handle();
            $this->info($days === [] ? 'No days to roll up.' : 'Rolled up '.count($days).' day(s), from '.$days[0].' to '.$days[count($days) - 1].'.');
        }

        if (! $this->option('no-prune')) {
            $pruned = $prune->handle();
            $this->info("Deleted {$pruned['events']} old event(s) and {$pruned['salts']} old salt(s).");
        }

        return self::SUCCESS;
    }
}
