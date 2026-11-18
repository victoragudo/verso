<?php

namespace App\Verso\Users\Http\Requests;

use App\Models\User;
use App\Verso\Access\Rules\AssignableRole;
use App\Verso\Core\Enums\Role;
use App\Verso\Core\Support\OptionalRoles;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Validates the invite form: name, email and role.
class InviteUserRequest extends FormRequest
{
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
        ];
    }

    /**
     * @return array{name: string, email: string, role: Role}
     */
    public function invitationData(): array
    {
        return [
            'name' => (string) $this->validated('name'),
            'email' => (string) $this->validated('email'),
            'role' => Role::from((string) $this->validated('role')),
        ];
    }
}
