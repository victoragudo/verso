<?php

namespace App\Verso\Users\Actions;

use App\Models\User;
use App\Verso\Users\Support\Invitations;
use LogicException;

// Sets the password of an invited user and activates the account.
class AcceptInvitation
{
    public function __construct(protected Invitations $invitations) {}

    public function handle(User $user, string $password): User
    {
        if (! $this->invitations->isPending($user)) {
            throw new LogicException('This user has no pending invitation.');
        }

        $user->password = $password;
        $user->forceFill(['email_verified_at' => now()]);
        $this->invitations->clear($user);
        $user->save();

        return $user;
    }
}
