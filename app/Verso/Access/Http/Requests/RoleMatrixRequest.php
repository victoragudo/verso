<?php

namespace App\Verso\Access\Http\Requests;

use App\Verso\Access\Capabilities\CapabilityRegistry;
use App\Verso\Access\Roles\Roles;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

// Validates a save of the roles x capabilities matrix: the full capability list of each changed role.
class RoleMatrixRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'roles' => ['required', 'array', 'min:1', $this->knownRoles(...)],
            'roles.*' => ['present', 'array'],
            'roles.*.*' => ['string', $this->knownCapability(...)],
        ];
    }

    /** @return array<string, list<string>> */
    public function changes(): array
    {
        $changes = [];

        foreach ((array) $this->validated('roles') as $slug => $capabilities) {
            $changes[(string) $slug] = array_values(array_unique(array_map('strval', (array) $capabilities)));
        }

        return $changes;
    }

    protected function knownRoles(string $attribute, mixed $value, Closure $fail): void
    {
        foreach (array_keys((array) $value) as $slug) {
            if (! app(Roles::class)->exists((string) $slug)) {
                $fail(__('The role ":role" does not exist.', ['role' => (string) $slug]));
            }
        }
    }

    protected function knownCapability(string $attribute, mixed $value, Closure $fail): void
    {
        if (! app(CapabilityRegistry::class)->has((string) $value)) {
            $fail(__('The capability ":capability" does not exist.', ['capability' => (string) $value]));
        }
    }
}
