<?php

namespace App\Verso\Users\Actions;

use App\Models\User;
use App\Verso\Core\Enums\Role;
use Illuminate\Support\Str;

// Creates a user without a known password and emails them an invitation link.
class InviteUser
{
    public function __construct(protected SendInvitation $sendInvitation) {}

    /**
     * @param  array{name: string, email: string, role: Role}  $data
     */
    public function handle(array $data, ?User $invitedBy = null): User
    {
        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Str::password(),
        ]);
        $user->role = $data['role'];
        $user->save();

        return $this->sendInvitation->handle($user, $invitedBy);
    }
}
