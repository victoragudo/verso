<?php

namespace App\Verso\Users\Actions;

use App\Models\User;
use App\Verso\Core\Enums\Role;

// Creates a user with a password chosen by an admin.
class CreateUser
{
    /**
     * @param  array{name: string, email: string, role: Role, password: string}  $data
     */
    public function handle(array $data): User
    {
        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
        $user->role = $data['role'];
        $user->forceFill(['email_verified_at' => now()]);
        $user->save();

        return $user;
    }
}
