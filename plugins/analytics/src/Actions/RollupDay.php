<?php

namespace Verso\Plugins\Analytics\Actions;

use Illuminate\Support\Facades\DB;
use Verso\Plugins\Analytics\Models\DailyStat;
use Verso\Plugins\Analytics\Reports\DayAggregator;

// Writes the daily rows of one day from its raw events. Running it again gives the same result.
class RollupDay
{
    public function __construct(
        protected DayAggregator $aggregator,
        protected int $chunk,
    ) {}

    // Returns the number of daily rows written.
    public function handle(string $day): int
    {
        $rows = $this->aggregator->rows($day);

        DB::transaction(function () use ($day, $rows): void {
            DailyStat::query()->where('day', $day)->delete();

            foreach (array_chunk($rows, max(1, $this->chunk)) as $chunk) {
                DailyStat::query()->insert($chunk);
            }
        });

        return count($rows);
    }
}
