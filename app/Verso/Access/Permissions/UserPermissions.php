<?php

namespace App\Verso\Access\Permissions;

use App\Models\User;
use App\Verso\Access\Capabilities\CapabilityRegistry;
use App\Verso\Access\Roles\Roles;
use App\Verso\Access\Support\ViewAs;
use App\Verso\Core\Enums\Role;
use WeakMap;

// Works out what a user may do: their roles, then single grants, then single denies.
class UserPermissions
{
    /** @var WeakMap<User, AccessSet> */
    protected WeakMap $cache;

    public function __construct(
        protected Roles $roles,
        protected CapabilityRegistry $capabilities,
        protected ViewAs $viewAs,
    ) {
        $this->cache = new WeakMap;
    }

    public function allows(User $user, string $capability): bool
    {
        return $this->for($user)->allows($capability);
    }

    public function for(User $user): AccessSet
    {
        return $this->cache[$user] ??= $this->resolve($user);
    }

    // Forget the cached answers, after roles or grants changed.
    public function forget(?User $user = null): void
    {
        if ($user === null) {
            $this->cache = new WeakMap;
            $this->roles->flush();

            return;
        }

        unset($this->cache[$user]);
    }

    /**
     * The capabilities of a list of roles, with what they imply. Used for previews and compare.
     *
     * @param  list<string>  $roles
     */
    public function forRoles(array $roles): AccessSet
    {
        if (in_array(Role::Admin->value, $roles, true)) {
            return new AccessSet([], all: true);
        }

        $keys = [];

        foreach ($roles as $slug) {
            foreach ($this->roles->find($slug)->capabilities ?? [] as $capability) {
                $keys[] = $capability;
            }
        }

        return new AccessSet($this->expand($keys));
    }

    protected function resolve(User $user): AccessSet
    {
        $previewed = $user->exists ? $this->viewAs->roleFor((int) $user->getKey()) : null;

        if ($previewed !== null) {
            return $this->forRoles([$previewed]);
        }

        $roles = $user->roleSlugs();

        // Administrators always keep everything, so a deny can never lock the site out.
        if (in_array(Role::Admin->value, $roles, true)) {
            return new AccessSet([], all: true);
        }

        $overrides = $user->capabilityOverrides();
        $granted = $this->forRoles($roles)->capabilities + $this->expand(array_keys(array_filter($overrides)));
        $denied = $this->expand(array_keys(array_filter($overrides, fn (bool $grant): bool => ! $grant)));

        return new AccessSet(array_diff_key($granted, $denied));
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, true>
     */
    protected function expand(array $keys): array
    {
        $set = [];

        foreach (array_unique($keys) as $key) {
            foreach ($this->capabilities->expand($key) as $capability) {
                $set[$capability] = true;
            }
        }

        return $set;
    }
}
