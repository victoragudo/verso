<?php

namespace App\Verso\Access\Actions;

use App\Models\User;
use App\Verso\Access\Events\RoleDeleted;
use App\Verso\Access\Models\StoredRole;
use App\Verso\Access\Permissions\UserPermissions;
use App\Verso\Access\Roles\RoleDefinition;
use App\Verso\Access\Support\AccessAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Deletes a custom role and takes it away from every user. Built-in roles can only be reset.
class DeleteRole
{
    public function __construct(
        protected AccessAudit $audit,
        protected UserPermissions $permissions,
    ) {}

    // Returns how many users lost the role.
    public function handle(User $actor, RoleDefinition $role): int
    {
        if ($role->builtIn) {
            throw ValidationException::withMessages(['role' => __(':name is a built-in role. You can clone it or reset it, but not delete it.', ['name' => $role->displayName()])]);
        }

        $users = DB::transaction(function () use ($role): int {
            $users = DB::table('role_user')->where('role', $role->slug)->delete();
            StoredRole::query()->where('slug', $role->slug)->delete();

            return $users;
        });

        $this->permissions->forget();
        RoleDeleted::dispatch($role);
        $this->audit->log($actor, AccessAudit::ROLE_DELETE, $role->slug, $role->capabilities, null, ['name' => $role->name, 'users' => $users]);

        return $users;
    }
}
