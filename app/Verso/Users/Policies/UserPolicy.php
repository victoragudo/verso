<?php

namespace App\Verso\Users\Policies;

use App\Models\User;
use App\Verso\Access\Support\EscalationGuard;
use App\Verso\Core\Enums\Capability;

// Who may see, add, edit, promote and delete users. manage_users gives all of it.
class UserPolicy
{
    public function __construct(protected EscalationGuard $guard) {}

    public function viewAny(User $user): bool
    {
        return $user->hasCapability(Capability::ListUsers);
    }

    public function create(User $user): bool
    {
        return $user->hasCapability(Capability::CreateUsers);
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasCapability(Capability::EditUsers) && $this->guard->canManage($user, $model);
    }

    // Change roles and single capabilities: never your own, never someone with more access than you.
    public function promote(User $user, User $model): bool
    {
        return $user->hasCapability(Capability::PromoteUsers) && $this->guard->canManage($user, $model);
    }

    // Users delete other users here. Their own account is deleted from the profile page.
    public function delete(User $user, User $model): bool
    {
        return $user->hasCapability(Capability::DeleteUsers) && ! $user->is($model) && $this->guard->canManage($user, $model);
    }
}
