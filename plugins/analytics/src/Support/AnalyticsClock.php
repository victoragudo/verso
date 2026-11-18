<?php

namespace Verso\Plugins\Analytics\Support;

use App\Verso\Core\Support\SiteTime;
use Carbon\CarbonImmutable;

// Gives "today" and day ranges in the site time zone, as Y-m-d strings.
class AnalyticsClock
{
    public function __construct(protected SiteTime $siteTime) {}

    public function now(): CarbonImmutable
    {
        return $this->siteTime->local(CarbonImmutable::now());
    }

    public function today(): string
    {
        return $this->now()->toDateString();
    }

    // The day that is $days days before today.
    public function daysAgo(int $days): string
    {
        return $this->now()->subDays($days)->toDateString();
    }

    /**
     * Every day of the last $days days, oldest first, today last.
     *
     * @return list<string>
     */
    public function lastDays(int $days): array
    {
        $today = $this->now()->startOfDay();
        $list = [];

        for ($offset = max(1, $days) - 1; $offset >= 0; $offset--) {
            $list[] = $today->subDays($offset)->toDateString();
        }

        return $list;
    }
}
