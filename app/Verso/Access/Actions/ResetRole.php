<?php

namespace App\Verso\Access\Actions;

use App\Models\User;
use App\Verso\Access\Models\StoredRole;
use App\Verso\Access\Permissions\UserPermissions;
use App\Verso\Access\Roles\RoleDefinition;
use App\Verso\Access\Roles\Roles;
use App\Verso\Access\Support\AccessAudit;
use App\Verso\Access\Support\EscalationGuard;

// Puts a built-in or plugin role back to the capabilities from code.
class ResetRole
{
    public function __construct(
        protected Roles $roles,
        protected EscalationGuard $guard,
        protected AccessAudit $audit,
        protected UserPermissions $permissions,
    ) {}

    public function handle(User $actor, RoleDefinition $role): RoleDefinition
    {
        $defaults = $this->roles->defaults($role->slug) ?? $role;

        $this->guard->ensureCanGrant($actor, array_values(array_diff($defaults->capabilities, $role->capabilities)));

        StoredRole::query()->where('slug', $role->slug)->delete();
        $this->permissions->forget();

        $this->audit->log($actor, AccessAudit::ROLE_RESET, $role->slug, $role->capabilities, $defaults->capabilities, ['name' => $defaults->name]);

        return $this->roles->find($role->slug) ?? $defaults;
    }
}
