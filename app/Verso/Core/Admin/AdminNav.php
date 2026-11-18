<?php

namespace App\Verso\Core\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Facade;

/**
 * Static access to the admin sidebar registry.
 *
 * @method static void add(NavItem $item)
 * @method static void remove(string $key)
 * @method static bool has(string $key)
 * @method static NavItem|null get(string $key)
 * @method static list<NavItem> all()
 * @method static list<array{key: string, label: string, href: string, icon: string, group: string, parent: string|null, keywords: list<string>, plugin: string|null}> forUser(?User $user)
 * @method static list<string> path(string $key)
 *
 * @see AdminNavRegistry
 */
class AdminNav extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AdminNavRegistry::class;
    }
}
