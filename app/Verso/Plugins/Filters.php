<?php

namespace App\Verso\Plugins;

use Illuminate\Support\Facades\Facade;

/**
 * Static access to the typed filter registry.
 *
 * @method static void add(string $filter, \Closure $callback, int $priority = 10)
 * @method static bool has(string $filter)
 * @method static void clear(string $filter)
 * @method static mixed apply(string $filter, mixed $value, mixed ...$context)
 *
 * @see FilterRegistry
 */
class Filters extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FilterRegistry::class;
    }
}
