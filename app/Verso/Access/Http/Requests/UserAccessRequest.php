<?php

namespace App\Verso\Access\Http\Requests;

use App\Models\User;
use App\Verso\Access\Capabilities\CapabilityRegistry;
use App\Verso\Access\Roles\Roles;
use App\Verso\Core\Enums\Role;
use App\Verso\Core\Support\OptionalRoles;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Validates the roles and single grants or denies of one user.
class UserAccessRequest extends FormRequest
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
            'role' => ['required', Rule::enum(Role::class)->only(app(OptionalRoles::class)->available())],
            'extra_roles' => ['present', 'array'],
            'extra_roles.*' => ['string', 'distinct', fn (string $attribute, mixed $value, Closure $fail) => app(Roles::class)->exists((string) $value) ? null : $fail(__('This role does not exist.'))],
            'grants' => ['present', 'array'],
            'grants.*' => ['string', 'distinct', $this->knownCapability(...)],
            'denies' => ['present', 'array'],
            'denies.*' => ['string', 'distinct', $this->knownCapability(...), Rule::notIn((array) $this->input('grants', []))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['denies.*.not_in' => __('A capability cannot be given and taken away at the same time.')];
    }

    public function role(): Role
    {
        return Role::from((string) $this->validated('role'));
    }

    /** @return list<string> */
    public function list(string $key): array
    {
        return array_values(array_map('strval', (array) $this->validated($key, [])));
    }

    protected function knownCapability(string $attribute, mixed $value, Closure $fail): void
    {
        if (! app(CapabilityRegistry::class)->has((string) $value)) {
            $fail(__('The capability ":capability" does not exist.', ['capability' => (string) $value]));
        }
    }
}
