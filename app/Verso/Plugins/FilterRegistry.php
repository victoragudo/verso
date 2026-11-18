<?php

namespace App\Verso\Plugins;

use App\Verso\Plugins\Contracts\Filter;
use App\Verso\Plugins\Exceptions\InvalidFilterResult;
use App\Verso\Plugins\Exceptions\UnknownFilter;
use Closure;

// Holds filter callbacks. Each callback gets the value and returns a new value of the same type.
class FilterRegistry
{
    /** @var array<class-string<Filter>, array<int, list<Closure>>> */
    protected array $callbacks = [];

    /**
     * Add a callback. Lower priorities run first.
     *
     * @param  class-string<Filter>  $filter
     */
    public function add(string $filter, Closure $callback, int $priority = 10): void
    {
        $this->ensureFilter($filter);

        $this->callbacks[$filter][$priority][] = $callback;
    }

    /**
     * @param  class-string<Filter>  $filter
     */
    public function has(string $filter): bool
    {
        return ($this->callbacks[$filter] ?? []) !== [];
    }

    /**
     * Remove all callbacks of one filter.
     *
     * @param  class-string<Filter>  $filter
     */
    public function clear(string $filter): void
    {
        unset($this->callbacks[$filter]);
    }

    /**
     * Run every callback in order. Each result must keep the type of the first value.
     *
     * @param  class-string<Filter>  $filter
     */
    public function apply(string $filter, mixed $value, mixed ...$context): mixed
    {
        $this->ensureFilter($filter);

        $expected = get_debug_type($value);

        foreach ($this->sorted($filter) as $callback) {
            $result = $callback($value, ...$context);
            $actual = get_debug_type($result);

            if ($value !== null && $actual !== $expected && ! (is_object($value) && $result instanceof $value)) {
                throw InvalidFilterResult::for($filter, $expected, $actual);
            }

            $value = $result;
        }

        return $value;
    }

    /**
     * @param  class-string<Filter>  $filter
     * @return list<Closure>
     */
    protected function sorted(string $filter): array
    {
        $byPriority = $this->callbacks[$filter] ?? [];
        ksort($byPriority);

        return array_merge(...array_values($byPriority));
    }

    protected function ensureFilter(string $filter): void
    {
        if (! is_subclass_of($filter, Filter::class)) {
            throw UnknownFilter::for($filter);
        }
    }
}
