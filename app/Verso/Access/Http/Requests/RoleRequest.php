<?php

namespace App\Verso\Access\Http\Requests;

use App\Verso\Access\Actions\CloneRole;
use App\Verso\Access\Capabilities\CapabilityRegistry;
use App\Verso\Access\Roles\RoleDefinition;
use App\Verso\Access\Roles\RoleRegistry;
use App\Verso\Access\Roles\Roles;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

// Validates a new role or the changes to a role.
class RoleRequest extends FormRequest
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
        $creating = $this->route('role') === null;

        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'slug' => $creating ? ['nullable', 'string', 'max:64', 'regex:'.RoleDefinition::SLUG_PATTERN, $this->freeSlug(...)] : ['prohibited'],
            'from' => $creating ? ['nullable', 'string', fn (string $attribute, mixed $value, Closure $fail) => app(Roles::class)->exists((string) $value) ? null : $fail(__('Pick a role to start from.'))] : ['prohibited'],
            'capabilities' => ['sometimes', 'array'],
            'capabilities.*' => ['string', 'distinct', $this->knownCapability(...)],
        ];
    }

    // The slug given, or a free one made from the name ("SEO manager" gives "seo_manager").
    public function slug(): string
    {
        $slug = (string) ($this->validated('slug') ?? '');

        return $slug !== '' ? $slug : app(CloneRole::class)->freeSlug(Str::ascii((string) $this->validated('name')));
    }

    /** @return list<string> */
    public function capabilities(): array
    {
        return array_values(array_map('strval', (array) $this->validated('capabilities', [])));
    }

    protected function freeSlug(string $attribute, mixed $value, Closure $fail): void
    {
        if (app(Roles::class)->exists((string) $value) || app(RoleRegistry::class)->isDeclared((string) $value)) {
            $fail(__('Another role already uses this short name.'));
        }
    }

    protected function knownCapability(string $attribute, mixed $value, Closure $fail): void
    {
        if (! app(CapabilityRegistry::class)->has((string) $value)) {
            $fail(__('The capability ":capability" does not exist.', ['capability' => (string) $value]));
        }
    }
}
