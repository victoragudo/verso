<?php

namespace App\Verso\Users\Http\Requests;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use App\Verso\Access\Rules\AssignableRole;
use App\Verso\Core\Enums\Role;
use App\Verso\Core\Support\OptionalRoles;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Validates the form an admin uses to create a user with a password.
class StoreUserRequest extends FormRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'role' => ['required', Rule::enum(Role::class)->only(app(OptionalRoles::class)->available()), new AssignableRole($this->user())],
            'password' => $this->passwordRules(),
        ];
    }

    /**
     * @return array{name: string, email: string, role: Role, password: string}
     */
    public function userData(): array
    {
        return [
            'name' => (string) $this->validated('name'),
            'email' => (string) $this->validated('email'),
            'role' => Role::from((string) $this->validated('role')),
            'password' => (string) $this->validated('password'),
        ];
    }
}
