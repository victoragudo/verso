<?php

namespace App\Verso\Access\Http\Presenters;

use App\Models\User;
use App\Verso\Access\Capabilities\CapabilityRegistry;
use App\Verso\Access\Roles\Roles;
use App\Verso\Access\Support\ViewAs;

// The access data every admin page gets: the "can" map and the "view as role" banner.
class SharedAccess
{
    public function __construct(
        protected CapabilityRegistry $capabilities,
        protected Roles $roles,
        protected ViewAs $viewAs,
    ) {}

    /**
     * Every capability key with true or false for this user.
     *
     * @return array<string, bool>
     */
    public function can(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $can = [];

        foreach ($this->capabilities->keys() as $key) {
            $can[$key] = $user->hasCapability($key);
        }

        return $can;
    }

    /**
     * The role being previewed, or null.
     *
     * @return array{role: string, name: string}|null
     */
    public function viewAs(): ?array
    {
        $role = $this->viewAs->role();

        if ($role === null) {
            return null;
        }

        return ['role' => $role, 'name' => $this->roles->find($role)?->displayName() ?? $role];
    }
}
