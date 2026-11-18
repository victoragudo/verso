<?php

namespace App\Verso\Access\Events;

use App\Verso\Access\Roles\RoleDefinition;
use Illuminate\Foundation\Events\Dispatchable;

// A custom role was created. basedOn is the role it was copied from, if any.
class RoleCreated
{
    use Dispatchable;

    public function __construct(public RoleDefinition $role) {}
}
