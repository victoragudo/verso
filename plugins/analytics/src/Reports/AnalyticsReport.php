<?php

namespace Verso\Plugins\Analytics\Reports;

use Verso\Plugins\Analytics\Enums\Device;
use Verso\Plugins\Analytics\Enums\Dimension;
use Verso\Plugins\Analytics\Models\AnalyticsEvent;
use Verso\Plugins\Analytics\Models\DailyStat;
use Verso\Plugins\Analytics\Support\AnalyticsClock;

/**
 * Builds the numbers for the analytics page from the daily table, plus live counts for days not rolled up yet.
 *
 * @phpstan-type Row array{day: string, dimension: string, value: string, views: int, visitors: int}
 * @phpstan-type Ranked array{value: string, views: int, visitors: int}
 */
class AnalyticsReport
{
    public function __construct(
        protected AnalyticsClock $clock,
        protected DayAggregator $aggregator,
        protected int $topLimit,
    ) {}

    // Unique visitors seen today. Used by the dashboard stat card.
    public function visitorsToday(): int
    {
        return $this->aggregator->visitors($this->clock->today());
    }

    /**
     * Totals, a daily series, top pages, top referrers and devices for the last $days days.
     *
     * @return array{
     *     from: string,
     *     to: string,
     *     totals: array{visitors: int, views: int},
     *     series: list<array{date: string, visitors: int, views: int}>,
     *     pages: list<Ranked>,
     *     referrers: list<Ranked>,
     *     devices: list<array{device: string, label: string, views: int, visitors: int, share: float}>
     * }
     */
    public function build(int $days): array
    {
        $dates = $this->clock->lastDays($days);
        $from = $dates[0];
        $to = $dates[count($dates) - 1];
        $rows = $this->rows($from, $to);

        $totals = array_filter($rows, fn (array $row): bool => $row['dimension'] === Dimension::Total->value);
        $byDay = [];

        foreach ($totals as $row) {
            $byDay[$row['day']] = $row;
        }

        $series = array_map(fn (string $date): array => [
            'date' => $date,
            'visitors' => $byDay[$date]['visitors'] ?? 0,
            'views' => $byDay[$date]['views'] ?? 0,
        ], $dates);

        $sum = ['visitors' => 0, 'views' => 0];

        foreach ($series as $point) {
            $sum['visitors'] += $point['visitors'];
            $sum['views'] += $point['views'];
        }

        return [
            'from' => $from,
            'to' => $to,
            'totals' => $sum,
            'series' => $series,
            'pages' => array_slice($this->ranked($rows, Dimension::Page), 0, max(1, $this->topLimit)),
            'referrers' => array_slice($this->ranked($rows, Dimension::Referrer), 0, max(1, $this->topLimit)),
            'devices' => $this->devices($rows, $sum['views']),
        ];
    }

    /**
     * Stored daily rows in the range, plus rows counted live for days that have no stored rows (like today).
     *
     * @return list<Row>
     */
    public function rows(string $from, string $to): array
    {
        $stored = DailyStat::query()
            ->whereBetween('day', [$from, $to])
            ->get()
            ->map(fn (DailyStat $stat): array => [
                'day' => substr((string) $stat->getAttribute('day'), 0, 10),
                'dimension' => (string) $stat->getAttribute('dimension'),
                'value' => (string) $stat->getAttribute('value'),
                'views' => (int) $stat->getAttribute('views'),
                'visitors' => (int) $stat->getAttribute('visitors'),
            ])
            ->values()
            ->all();

        $storedDays = [];

        foreach ($stored as $row) {
            if ($row['dimension'] === Dimension::Total->value) {
                $storedDays[$row['day']] = true;
            }
        }

        $liveDays = AnalyticsEvent::query()
            ->whereBetween('day', [$from, $to])
            ->distinct()
            ->pluck('day')
            ->map(fn (mixed $day): string => substr((string) $day, 0, 10))
            ->reject(fn (string $day): bool => isset($storedDays[$day]));

        foreach ($liveDays as $day) {
            array_push($stored, ...$this->aggregator->rows($day));
        }

        return array_values($stored);
    }

    /**
     * Sum one dimension over the range, most views first.
     *
     * @param  list<Row>  $rows
     * @return list<Ranked>
     */
    protected function ranked(array $rows, Dimension $dimension): array
    {
        $sums = [];

        foreach ($rows as $row) {
            if ($row['dimension'] !== $dimension->value) {
                continue;
            }

            $sums[$row['value']] ??= ['value' => $row['value'], 'views' => 0, 'visitors' => 0];
            $sums[$row['value']]['views'] += $row['views'];
            $sums[$row['value']]['visitors'] += $row['visitors'];
        }

        $list = array_values($sums);

        usort($list, fn (array $a, array $b): int => [$b['views'], $b['visitors'], $a['value']] <=> [$a['views'], $a['visitors'], $b['value']]);

        return $list;
    }

    /**
     * Every device bucket with its views and share of all views, in a fixed order.
     *
     * @param  list<Row>  $rows
     * @return list<array{device: string, label: string, views: int, visitors: int, share: float}>
     */
    protected function devices(array $rows, int $totalViews): array
    {
        $ranked = [];

        foreach ($this->ranked($rows, Dimension::Device) as $item) {
            $ranked[$item['value']] = $item;
        }

        return array_map(fn (Device $device): array => [
            'device' => $device->value,
            'label' => $device->label(),
            'views' => $ranked[$device->value]['views'] ?? 0,
            'visitors' => $ranked[$device->value]['visitors'] ?? 0,
            'share' => $totalViews > 0 ? round(($ranked[$device->value]['views'] ?? 0) / $totalViews, 4) : 0.0,
        ], Device::cases());
    }
}
