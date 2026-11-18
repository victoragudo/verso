<?php

namespace App\Verso\Users\Http\Requests;

use App\Models\User;
use App\Verso\Access\Rules\AssignableRole;
use App\Verso\Core\Enums\Role;
use App\Verso\Core\Support\OptionalRoles;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Validates a role change.
class UpdateUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User && ($this->user()?->can('promote', $target) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(Role::class)->only(app(OptionalRoles::class)->available()), new AssignableRole($this->user())],
        ];
    }

    public function role(): Role
    {
        return Role::from((string) $this->validated('role'));
    }
}
