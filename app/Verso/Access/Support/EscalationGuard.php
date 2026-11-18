<?php

namespace App\Verso\Access\Support;

use App\Models\User;
use App\Verso\Access\Capabilities\CapabilityRegistry;
use App\Verso\Access\Permissions\UserPermissions;
use App\Verso\Access\Roles\Roles;
use Illuminate\Validation\ValidationException;

// Stops people from giving more than they have, and from changing their own access.
class EscalationGuard
{
    public function __construct(
        protected UserPermissions $permissions,
        protected CapabilityRegistry $capabilities,
        protected Roles $roles,
    ) {}

    /**
     * Capabilities from the list that the actor does not have (with what they imply).
     *
     * @param  list<string>  $capabilities
     * @return list<string>
     */
    public function missing(User $actor, array $capabilities): array
    {
        $missing = [];

        foreach ($capabilities as $capability) {
            foreach ($this->capabilities->expand($capability) as $key) {
                if (! $actor->hasCapability($key)) {
                    $missing[] = $key;
                }
            }
        }

        return array_values(array_unique($missing));
    }

    /**
     * @param  list<string>  $capabilities
     */
    public function ensureCanGrant(User $actor, array $capabilities, string $field = 'capabilities'): void
    {
        $missing = $this->missing($actor, $capabilities);

        if ($missing !== []) {
            throw ValidationException::withMessages([$field => __('You cannot give capabilities you do not have yourself: :capabilities.', ['capabilities' => implode(', ', $this->labels($missing))])]);
        }
    }

    // A role can be given only when the actor has every capability of it.
    public function canAssignRole(User $actor, string $role): bool
    {
        if ($actor->isAdmin()) {
            return true;
        }

        $definition = $this->roles->find($role);

        return $definition !== null && ! $definition->locked && $this->missing($actor, $definition->capabilities) === [];
    }

    public function ensureCanAssignRole(User $actor, string $role, string $field = 'roles'): void
    {
        if (! $this->canAssignRole($actor, $role)) {
            throw ValidationException::withMessages([$field => __('You cannot give the role ":name", because it has capabilities you do not have.', ['name' => $this->roles->find($role)?->displayName() ?? $role])]);
        }
    }

    // Only people with at least the same access change someone else. People may change their own
    // access too, but every role or capability they add is checked, so they can never raise it.
    public function canManage(User $actor, User $target): bool
    {
        if ($actor->is($target) || $actor->isAdmin()) {
            return true;
        }

        if ($target->isAdmin()) {
            return false;
        }

        $access = $this->permissions->for($target);

        return $this->missing($actor, array_keys($access->capabilities)) === [];
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    protected function labels(array $keys): array
    {
        return array_map(fn (string $key): string => $this->capabilities->get($key)?->displayLabel() ?? $key, $keys);
    }
}
