<?php

namespace App\Verso\Theme\Benchmark;

use InvalidArgumentException;

// Response times of one route, in milliseconds, with percentile helpers.
class Samples
{
    /** @var list<float> sorted from fast to slow */
    protected array $times;

    /**
     * @param  list<float>  $times
     */
    public function __construct(array $times)
    {
        if ($times === []) {
            throw new InvalidArgumentException('A benchmark needs at least one sample.');
        }

        sort($times);
        $this->times = $times;
    }

    // The nearest-rank percentile, like 95 for p95.
    public function percentile(int $percent): float
    {
        $percent = max(1, min(100, $percent));
        $rank = (int) ceil($percent / 100 * count($this->times));

        return $this->times[max(0, $rank - 1)];
    }

    public function mean(): float
    {
        return array_sum($this->times) / count($this->times);
    }

    public function count(): int
    {
        return count($this->times);
    }
}
