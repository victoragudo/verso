<?php

namespace App\Verso\Content\Enums;

// The entry actions the EntryPolicy checks.
enum EntryAbility: string
{
    case ViewAny = 'viewAny';
    case Create = 'create';
    case Update = 'update';
    case Publish = 'publish';
    case Delete = 'delete';
    case Restore = 'restore';
}
