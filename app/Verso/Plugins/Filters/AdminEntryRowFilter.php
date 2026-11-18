<?php

namespace App\Verso\Plugins\Filters;

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Plugins\Contracts\Filter;
use App\Verso\Plugins\FilterRegistry;

// Changes the data of one row of the admin entries table, for example to feed a plugin column.
// Callbacks: fn (array $row, Entry $entry, User $user): array.
final class AdminEntryRowFilter implements Filter
{
    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function apply(array $row, Entry $entry, User $user): array
    {
        /** @var array<string, mixed> */
        return (array) app(FilterRegistry::class)->apply(self::class, $row, $entry, $user);
    }
}
