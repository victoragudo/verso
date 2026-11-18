<?php

namespace App\Verso\Users\Actions;

use App\Models\User;
use App\Verso\Access\Support\AccessAudit;
use App\Verso\Core\Enums\Role;
use App\Verso\Users\Support\AdminGuard;
use Illuminate\Support\Facades\DB;

// Gives a user a new role. The last admin cannot lose the admin role.
class ChangeUserRole
{
    public function __construct(
        protected AdminGuard $guard,
        protected AccessAudit $audit,
    ) {}

    // The new main role is no longer an extra role of the user. $actor is who made the change, for the audit log.
    public function handle(User $user, Role $role, ?User $actor = null): User
    {
        $this->guard->ensureCanChangeRole($user, $role);

        $old = $user->role;
        $oldRoles = $user->roleSlugs();

        DB::transaction(function () use ($user, $role): void {
            DB::table('role_user')->where('user_id', $user->id)->where('role', $role->value)->delete();
            $user->refreshAccess();
            $user->role = $role;
            $user->save();
        });

        $user->refreshAccess();

        if ($old !== $role) {
            $details = ['user' => $user->name, 'user_id' => $user->id];
            $this->audit->log($actor, AccessAudit::USER_ROLE, $old->value, 'removed', null, $details);
            $this->audit->log($actor, AccessAudit::USER_ROLE, $role->value, null, 'added', $details);
            $this->audit->log($actor, AccessAudit::USER_ACCESS, AccessAudit::userKey($user), ['roles' => $oldRoles], ['roles' => $user->roleSlugs()], $details);
        }

        return $user;
    }
}
