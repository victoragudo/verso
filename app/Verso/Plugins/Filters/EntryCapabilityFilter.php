<?php

namespace App\Verso\Plugins\Filters;

use App\Models\User;
use App\Verso\Content\Enums\EntryAbility;
use App\Verso\Content\Models\Entry;
use App\Verso\Plugins\Contracts\Filter;
use App\Verso\Plugins\FilterRegistry;

// Changes the answer of an entry permission check (view, create, update, publish, delete, restore).
// Callbacks: fn (bool $allowed, User $user, EntryAbility $ability, ?Entry $entry): bool.
final class EntryCapabilityFilter implements Filter
{
    public static function apply(bool $allowed, User $user, EntryAbility $ability, ?Entry $entry = null): bool
    {
        return (bool) app(FilterRegistry::class)->apply(self::class, $allowed, $user, $ability, $entry);
    }
}
