<?php

namespace App\Verso\Access\Actions;

use App\Models\User;
use App\Verso\Access\Roles\RoleDefinition;
use App\Verso\Access\Roles\RoleRegistry;
use App\Verso\Access\Roles\Roles;
use Illuminate\Support\Str;

// Makes a custom role with the same capabilities as another role.
class CloneRole
{
    public function __construct(
        protected Roles $roles,
        protected SaveRole $saveRole,
        protected RoleRegistry $registry,
    ) {}

    public function handle(User $actor, RoleDefinition $source, ?string $name = null): RoleDefinition
    {
        $name = trim((string) $name) !== '' ? trim((string) $name) : __(':name (copy)', ['name' => $source->displayName()]);

        return $this->saveRole->handle($actor, null, [
            'slug' => $this->freeSlug(Str::ascii($name)),
            'name' => $name,
            'description' => $source->description,
            'capabilities' => $source->capabilities,
            'based_on' => $source->slug,
        ]);
    }

    // A slug that no role uses yet: "editor_copy", then "editor_copy_2" and so on.
    public function freeSlug(string $wanted): string
    {
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($wanted)), '_');
        $base = preg_match('/^[a-z]/', $base) === 1 ? substr($base, 0, 56) : 'role_'.substr($base, 0, 51);
        $base = rtrim($base, '_');
        $slug = $base;

        for ($number = 2; $this->roles->exists($slug) || $this->registry->isDeclared($slug); $number++) {
            $slug = $base.'_'.$number;
        }

        return $slug;
    }
}
