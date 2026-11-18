<?php

namespace App\Verso\Core\Support;

use App\Verso\Core\Enums\Role;

// Keeps the optional roles that plugins turned on. Only these can be given to users.
class OptionalRoles
{
    /** @var array<string, Role> */
    protected array $enabled = [];

    public function enable(Role $role): void
    {
        if ($role->isOptional()) {
            $this->enabled[$role->value] = $role;
        }
    }

    public function isEnabled(Role $role): bool
    {
        return isset($this->enabled[$role->value]);
    }

    /**
     * Roles people can pick: every built-in role plus the optional roles that are on.
     *
     * @return list<Role>
     */
    public function available(): array
    {
        return array_values(array_filter(
            Role::cases(),
            fn (Role $role): bool => ! $role->isOptional() || $this->isEnabled($role),
        ));
    }
}
