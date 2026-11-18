<?php

namespace App\Verso\Users\Support;

use App\Models\User;
use App\Verso\Core\Enums\Role;
use App\Verso\Users\Exceptions\LastAdminException;

// Makes sure the site always keeps at least one administrator (as main role or as an extra role).
class AdminGuard
{
    // True when the user is stored as an admin and no other admin exists.
    public function isLastAdmin(User $user): bool
    {
        if (! $user->exists || ! $this->isStoredAdmin($user)) {
            return false;
        }

        return User::query()->withRole(Role::Admin)->whereKeyNot($user->getKey())->doesntExist();
    }

    // Stops a main role change that would remove the last admin.
    public function ensureCanChangeRole(User $user, Role $newRole): void
    {
        $this->ensureKeepsAdmin($user, [$newRole->value, ...$user->extraRoles()]);
    }

    // Stops any change of roles that would remove the last admin.
    /** @param  list<string>  $newRoles */
    public function ensureKeepsAdmin(User $user, array $newRoles): void
    {
        if (! in_array(Role::Admin->value, $newRoles, true) && $this->isLastAdmin($user)) {
            throw LastAdminException::forRoleChange();
        }
    }

    public function ensureCanDelete(User $user): void
    {
        if ($this->isLastAdmin($user)) {
            throw LastAdminException::forDelete();
        }
    }

    // The roles saved in the database, before any unsaved change, include the admin role.
    protected function isStoredAdmin(User $user): bool
    {
        $role = $user->getOriginal('role');
        $role = $role instanceof Role ? $role : Role::tryFrom((string) $role);

        return $role === Role::Admin || in_array(Role::Admin->value, $user->extraRoles(), true);
    }
}
