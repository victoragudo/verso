<?php

namespace Verso\Plugins\Analytics\Actions;

use Verso\Plugins\Analytics\Enums\Dimension;
use Verso\Plugins\Analytics\Models\AnalyticsEvent;
use Verso\Plugins\Analytics\Models\DailyStat;
use Verso\Plugins\Analytics\Support\AnalyticsClock;

// Rolls up every past day that has events but no daily rows yet. Today is never rolled up.
class RollupPendingDays
{
    public function __construct(
        protected RollupDay $rollupDay,
        protected AnalyticsClock $clock,
    ) {}

    /**
     * @return list<string> the days that were rolled up
     */
    public function handle(): array
    {
        $done = [];

        foreach ($this->pendingDays() as $day) {
            $this->rollupDay->handle($day);
            $done[] = $day;
        }

        return $done;
    }

    /**
     * @return list<string>
     */
    public function pendingDays(): array
    {
        $rolled = DailyStat::query()->where('dimension', Dimension::Total->value)->select('day');

        return array_values(AnalyticsEvent::query()
            ->where('day', '<', $this->clock->today())
            ->whereNotIn('day', $rolled)
            ->distinct()
            ->orderBy('day')
            ->pluck('day')
            ->map(fn (mixed $day): string => substr((string) $day, 0, 10))
            ->all());
    }
}
