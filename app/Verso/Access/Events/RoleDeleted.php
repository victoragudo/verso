<?php

namespace App\Verso\Access\Events;

use App\Verso\Access\Roles\RoleDefinition;
use Illuminate\Foundation\Events\Dispatchable;

// A custom role was deleted. Modules that keep role lists can forget it.
class RoleDeleted
{
    use Dispatchable;

    public function __construct(public RoleDefinition $role) {}
}
