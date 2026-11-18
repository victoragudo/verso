<?php

namespace App\Verso\Users\Actions;

use App\Models\User;
use App\Verso\Users\Notifications\UserInvitation;
use App\Verso\Users\Support\Invitations;

// Sends a new invitation email. Older links of the same user stop working.
class SendInvitation
{
    public function __construct(protected Invitations $invitations) {}

    public function handle(User $user, ?User $invitedBy = null): User
    {
        $this->invitations->markInvited($user);
        $user->save();

        $user->notify(new UserInvitation(
            url: $this->invitations->url($user),
            expiresHours: $this->invitations->expiresHours(),
            invitedBy: $invitedBy?->name,
        ));

        return $user;
    }
}
