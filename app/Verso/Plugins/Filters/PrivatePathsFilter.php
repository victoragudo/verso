<?php

namespace App\Verso\Plugins\Filters;

use App\Verso\Plugins\Contracts\Filter;
use App\Verso\Plugins\FilterRegistry;

// Lists public paths that belong to one visitor (checkout, orders, signed links). Callbacks: fn (array $patterns): array.
final class PrivatePathsFilter implements Filter
{
    /**
     * Patterns like "/checkout" (the path and everything under it) or "/orders/*" (wildcards).
     *
     * @param  list<string>  $patterns
     * @return list<string>
     */
    public static function apply(array $patterns = []): array
    {
        $result = app(FilterRegistry::class)->apply(self::class, $patterns);

        return array_values(array_filter(
            array_map(strval(...), is_array($result) ? $result : []),
            fn (string $pattern): bool => trim($pattern) !== '',
        ));
    }
}
