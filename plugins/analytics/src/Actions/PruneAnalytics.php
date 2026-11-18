<?php

namespace Verso\Plugins\Analytics\Actions;

use Verso\Plugins\Analytics\Models\AnalyticsEvent;
use Verso\Plugins\Analytics\Support\AnalyticsClock;
use Verso\Plugins\Analytics\Support\AnalyticsSettings;
use Verso\Plugins\Analytics\Support\VisitorHasher;

// Deletes raw events older than the retention setting and the salts of past days.
class PruneAnalytics
{
    public function __construct(
        protected AnalyticsSettings $settings,
        protected AnalyticsClock $clock,
        protected VisitorHasher $hasher,
    ) {}

    /**
     * @return array{events: int, salts: int}
     */
    public function handle(): array
    {
        $keepFrom = $this->clock->daysAgo($this->settings->retentionDays() - 1);

        return [
            'events' => AnalyticsEvent::query()->where('day', '<', $keepFrom)->delete(),
            'salts' => $this->hasher->deleteBefore($this->clock->today()),
        ];
    }
}
