<?php

namespace Verso\Plugins\Analytics\Reports;

use Illuminate\Database\Eloquent\Builder;
use Verso\Plugins\Analytics\Enums\Dimension;
use Verso\Plugins\Analytics\Models\AnalyticsEvent;

// Counts the raw events of one day into daily rows: the total, each page, each referrer and each device.
class DayAggregator
{
    /**
     * @return list<array{day: string, dimension: string, value: string, views: int, visitors: int}>
     */
    public function rows(string $day): array
    {
        $rows = [];

        $total = $this->events($day)
            ->selectRaw('COUNT(*) as views, COUNT(DISTINCT visitor) as visitors')
            ->toBase()
            ->first();

        $views = (int) ($total->views ?? 0);

        if ($views === 0) {
            return [];
        }

        $rows[] = $this->row($day, Dimension::Total, '', $views, (int) ($total->visitors ?? 0));

        foreach ([Dimension::Page->value => 'path', Dimension::Referrer->value => 'referrer_host', Dimension::Device->value => 'device'] as $dimension => $column) {
            $groups = $this->events($day)
                ->whereNotNull($column)
                ->selectRaw("{$column} as value, COUNT(*) as views, COUNT(DISTINCT visitor) as visitors")
                ->groupBy($column)
                ->toBase()
                ->get();

            foreach ($groups as $group) {
                $rows[] = $this->row($day, Dimension::from($dimension), (string) $group->value, (int) $group->views, (int) $group->visitors);
            }
        }

        return $rows;
    }

    // Unique visitors of one day.
    public function visitors(string $day): int
    {
        return (int) $this->events($day)->distinct()->count('visitor');
    }

    /**
     * @return Builder<AnalyticsEvent>
     */
    protected function events(string $day): Builder
    {
        return AnalyticsEvent::query()->where('day', $day);
    }

    /**
     * @return array{day: string, dimension: string, value: string, views: int, visitors: int}
     */
    protected function row(string $day, Dimension $dimension, string $value, int $views, int $visitors): array
    {
        return ['day' => $day, 'dimension' => $dimension->value, 'value' => $value, 'views' => $views, 'visitors' => $visitors];
    }
}
