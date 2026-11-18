<?php

namespace App\Verso\Plugins\Filters;

use App\Verso\Content\Models\Entry;
use App\Verso\Plugins\Contracts\Filter;
use App\Verso\Plugins\FilterRegistry;

// Changes the JSON data of an entry in the public API. Callbacks: fn (array $data, Entry $entry): array.
final class ApiEntryFilter implements Filter
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function apply(array $data, Entry $entry): array
    {
        /** @var array<string, mixed> */
        return (array) app(FilterRegistry::class)->apply(self::class, $data, $entry);
    }
}
