<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Verso\Core\Enums\Role;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $user = new User([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);
        $user->role = $this->initialRole();
        $user->save();

        return $user;
    }

    // The first user of the site becomes admin. Others get the default role.
    protected function initialRole(): Role
    {
        if (User::query()->doesntExist()) {
            return Role::Admin;
        }

        return Role::tryFrom((string) config('verso.users.default_role')) ?? Role::Subscriber;
    }
}
