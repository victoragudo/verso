<?php

namespace App\Verso\Access\Actions;

use App\Models\User;
use App\Verso\Access\Events\RoleCreated;
use App\Verso\Access\Models\StoredRole;
use App\Verso\Access\Permissions\UserPermissions;
use App\Verso\Access\Roles\RoleDefinition;
use App\Verso\Access\Roles\Roles;
use App\Verso\Access\Support\AccessAudit;
use App\Verso\Access\Support\EscalationGuard;
use Illuminate\Validation\ValidationException;
use LogicException;

// Creates a custom role or changes any role except the administrator. Built-in roles keep only their changes.
class SaveRole
{
    public function __construct(
        protected Roles $roles,
        protected EscalationGuard $guard,
        protected AccessAudit $audit,
        protected UserPermissions $permissions,
    ) {}

    /**
     * @param  array{slug?: string, name: string, description?: string|null, capabilities: list<string>, based_on?: string|null}  $data
     */
    public function handle(User $actor, ?RoleDefinition $role, array $data): RoleDefinition
    {
        if ($role?->locked) {
            throw ValidationException::withMessages(['capabilities' => __('The administrator role always has every capability and cannot be changed.')]);
        }

        $capabilities = array_values(array_unique($data['capabilities']));
        $before = $role->capabilities ?? [];

        // Only new capabilities need checking: people may keep what a role already has.
        $this->guard->ensureCanGrant($actor, array_values(array_diff($capabilities, $before)));

        $slug = $role->slug ?? (string) ($data['slug'] ?? '');
        $name = $this->storedText($role, trim($data['name']), $role?->name, $role?->displayName());
        $description = $this->storedText($role, trim((string) ($data['description'] ?? '')), $role?->description, $role?->displayDescription());

        if ($role === null || ! $role->builtIn) {
            StoredRole::query()->updateOrCreate(['slug' => $slug], [
                'name' => $name,
                'description' => $description === '' ? null : $description,
                'capabilities' => $capabilities,
                'removed_capabilities' => null,
                'based_on' => $role->basedOn ?? ($data['based_on'] ?? null),
            ]);
        } else {
            $this->saveChanges($role, $name, $description, $capabilities);
        }

        $this->permissions->forget();
        $saved = $this->roles->find($slug) ?? throw new LogicException("The role [{$slug}] was not saved.");

        $this->audit->log($actor, $role === null ? AccessAudit::ROLE_CREATE : AccessAudit::ROLE_UPDATE, $slug, $role === null ? null : $before, $saved->capabilities, array_filter([
            'name' => $saved->name,
            'added' => array_values(array_diff($saved->capabilities, $before)),
            'removed' => array_values(array_diff($before, $saved->capabilities)),
            'renamed' => $role !== null && $role->name !== $saved->name ? $role->name : null,
            'based_on' => $role === null ? ($data['based_on'] ?? null) : null,
        ], fn (mixed $value): bool => $value !== null && $value !== []));

        if ($role === null) {
            RoleCreated::dispatch($saved);
        }

        return $saved;
    }

    protected function storedText(?RoleDefinition $role, string $text, ?string $stored, ?string $shown): string
    {
        return $role !== null && $role->builtIn && $stored !== null && $text === $shown ? $stored : $text;
    }

    // Built-in and plugin roles store only what differs from the code, so new defaults still arrive.
    /** @param  list<string>  $capabilities */
    protected function saveChanges(RoleDefinition $role, string $name, string $description, array $capabilities): void
    {
        $defaults = $this->roles->defaults($role->slug) ?? $role;
        $added = array_values(array_diff($capabilities, $defaults->capabilities));
        $removed = array_values(array_diff($defaults->capabilities, $capabilities));

        if ($added === [] && $removed === [] && $name === $defaults->name && $description === $defaults->description) {
            StoredRole::query()->where('slug', $role->slug)->delete();

            return;
        }

        StoredRole::query()->updateOrCreate(['slug' => $role->slug], [
            'name' => $name,
            'description' => $description,
            'capabilities' => $added,
            'removed_capabilities' => $removed,
        ]);
    }
}
