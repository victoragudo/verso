<?php

namespace App\Verso\Access\Actions;

use App\Models\User;
use App\Verso\Access\Support\AccessAudit;
use App\Verso\Access\Support\EscalationGuard;
use App\Verso\Core\Enums\Role;
use App\Verso\Users\Support\AdminGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

// Saves the roles and single grants or denies of one user, with every safety rule.
class SaveUserAccess
{
    public function __construct(
        protected EscalationGuard $guard,
        protected AdminGuard $adminGuard,
        protected AccessAudit $audit,
    ) {}

    /**
     * @param  list<string>  $extraRoles
     * @param  list<string>  $grants
     * @param  list<string>  $denies
     */
    public function handle(User $actor, User $user, Role $role, array $extraRoles, array $grants, array $denies): User
    {
        if (! $this->guard->canManage($actor, $user)) {
            throw new AuthorizationException(__('You cannot change the access of this user.'));
        }

        $extraRoles = array_values(array_unique(array_diff($extraRoles, [$role->value])));
        $oldRoles = $user->roleSlugs();
        $newRoles = [$role->value, ...$extraRoles];

        foreach (array_diff($newRoles, $oldRoles) as $added) {
            $this->guard->ensureCanAssignRole($actor, $added, $added === $role->value ? 'role' : 'extra_roles');
        }

        $oldOverrides = $user->capabilityOverrides();
        // New grants, and denies that are taken away, both give capabilities back.
        $oldDenies = array_keys(array_filter($oldOverrides, fn (bool $granted): bool => ! $granted));
        $this->guard->ensureCanGrant($actor, array_values(array_diff($grants, array_keys(array_filter($oldOverrides)))), 'grants');
        $this->guard->ensureCanGrant($actor, array_values(array_diff($oldDenies, $denies)), 'denies');
        $this->adminGuard->ensureKeepsAdmin($user, $newRoles);

        DB::transaction(function () use ($user, $role, $extraRoles, $grants, $denies): void {
            DB::table('role_user')->where('user_id', $user->id)->delete();
            DB::table('role_user')->insert(array_map(fn (string $slug): array => ['user_id' => $user->id, 'role' => $slug], $extraRoles));

            DB::table('user_capabilities')->where('user_id', $user->id)->delete();
            DB::table('user_capabilities')->insert([
                ...array_map(fn (string $capability): array => ['user_id' => $user->id, 'capability' => $capability, 'granted' => true], $grants),
                ...array_map(fn (string $capability): array => ['user_id' => $user->id, 'capability' => $capability, 'granted' => false], $denies),
            ]);

            $user->refreshAccess();
            $user->role = $role;
            $user->save();
        });

        $user->refreshAccess();
        $this->log($actor, $user, $oldRoles, $newRoles, $oldOverrides, $user->capabilityOverrides());

        return $user;
    }

    /**
     * @param  list<string>  $oldRoles
     * @param  list<string>  $newRoles
     * @param  array<string, bool>  $oldOverrides
     * @param  array<string, bool>  $newOverrides
     */
    protected function log(User $actor, User $user, array $oldRoles, array $newRoles, array $oldOverrides, array $newOverrides): void
    {
        $details = ['user' => $user->name, 'user_id' => $user->id];

        foreach (array_diff($newRoles, $oldRoles) as $role) {
            $this->audit->log($actor, AccessAudit::USER_ROLE, $role, null, 'added', $details);
        }

        foreach (array_diff($oldRoles, $newRoles) as $role) {
            $this->audit->log($actor, AccessAudit::USER_ROLE, $role, 'removed', null, $details);
        }

        if ($oldRoles != $newRoles || $oldOverrides != $newOverrides) {
            $this->audit->log($actor, AccessAudit::USER_ACCESS, AccessAudit::userKey($user), ['roles' => $oldRoles, 'overrides' => $oldOverrides], ['roles' => $newRoles, 'overrides' => $newOverrides], $details);
        }
    }
}
