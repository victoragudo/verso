<?php

namespace App\Verso\Access\Http\Presenters;

use App\Models\User;
use App\Verso\Access\Capabilities\CapabilityDefinition;
use App\Verso\Access\Capabilities\CapabilityRegistry;
use App\Verso\Access\Roles\RoleDefinition;
use App\Verso\Access\Roles\Roles;
use App\Verso\Access\Support\EscalationGuard;
use Illuminate\Support\Facades\DB;

// Turns roles and capabilities into the props of the roles admin pages.
class RolePresenter
{
    public function __construct(
        protected Roles $roles,
        protected CapabilityRegistry $capabilities,
        protected EscalationGuard $guard,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function capabilities(): array
    {
        return array_map(fn (CapabilityDefinition $definition): array => $definition->present(), $this->capabilities->all());
    }

    /**
     * Every role with its number of users and what the viewer may do with it.
     *
     * @return list<array<string, mixed>>
     */
    public function roles(User $viewer): array
    {
        $counts = $this->userCounts();

        return array_values(array_map(fn (RoleDefinition $role): array => $this->role($role, $viewer, $counts[$role->slug] ?? 0), $this->roles->all()));
    }

    /**
     * @return array<string, mixed>
     */
    public function role(RoleDefinition $role, User $viewer, ?int $users = null): array
    {
        return [
            ...$role->present(),
            // Admins have every capability, also ones added later by plugins.
            'capabilities' => $role->locked ? $this->capabilities->keys() : $role->capabilities,
            'users' => $users ?? ($this->userCounts()[$role->slug] ?? 0),
            'canAssign' => $this->guard->canAssignRole($viewer, $role->slug),
        ];
    }

    /**
     * Capabilities the viewer may tick: the ones they have.
     *
     * @return list<string>
     */
    public function grantable(User $viewer): array
    {
        return array_values(array_filter($this->capabilities->keys(), fn (string $key): bool => $viewer->hasCapability($key)));
    }

    /**
     * Number of users per role, main and extra roles together.
     *
     * @return array<string, int>
     */
    public function userCounts(): array
    {
        $counts = [];

        foreach ([User::query()->toBase()->select('role', DB::raw('count(*) as aggregate'))->groupBy('role')->get(), DB::table('role_user')->select('role', DB::raw('count(*) as aggregate'))->groupBy('role')->get()] as $rows) {
            foreach ($rows as $row) {
                $counts[(string) $row->role] = ($counts[(string) $row->role] ?? 0) + (int) $row->aggregate;
            }
        }

        return $counts;
    }
}
