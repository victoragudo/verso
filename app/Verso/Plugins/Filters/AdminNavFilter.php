<?php

namespace App\Verso\Plugins\Filters;

use App\Models\User;
use App\Verso\Core\Admin\NavItem;
use App\Verso\Plugins\Contracts\Filter;
use App\Verso\Plugins\FilterRegistry;

// Changes the admin sidebar items one user sees: add, hide, rename or reorder them.
// Callbacks: fn (array $items, ?User $user): array. Each item is a NavItem.
final class AdminNavFilter implements Filter
{
    /**
     * @param  list<NavItem>  $items
     * @return list<NavItem>
     */
    public static function apply(array $items, ?User $user): array
    {
        $items = (array) app(FilterRegistry::class)->apply(self::class, $items, $user);

        return array_values(array_filter($items, fn (mixed $item): bool => $item instanceof NavItem));
    }
}
