<?php

namespace Verso\Plugins\HarborTides;

use Illuminate\Support\Facades\DB;

// Reads the next tides from the plugin table.
class TideTable
{
    /**
     * @return list<array{time: string, height: float}>
     */
    public function next(string $harbour, int $limit = 4): array
    {
        return DB::table('harbor_tide_times')
            ->where('harbour', $harbour)
            ->orderBy('time')
            ->limit($limit)
            ->get(['time', 'height'])
            ->map(fn (object $row): array => ['time' => (string) $row->time, 'height' => (float) $row->height])
            ->all();
    }
}
