<?php

namespace App\Verso\Access\Roles;

use App\Verso\Access\Capabilities\CapabilityRegistry;
use App\Verso\Access\Models\StoredRole;
use App\Verso\Core\Enums\Role;
use Illuminate\Support\Facades\Schema;

// Every role of the site: roles from code with the changes saved by admins, plus custom roles.
class Roles
{
    /** @var array<string, RoleDefinition>|null */
    protected ?array $cache = null;

    public function __construct(
        protected RoleRegistry $registry,
        protected CapabilityRegistry $capabilities,
    ) {}

    /**
     * Built-in roles first (admin, editor ...), then plugin roles, then custom roles by name.
     *
     * @return array<string, RoleDefinition>
     */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $roles = $this->registry->all();
        $custom = [];

        foreach ($this->storedRoles() as $stored) {
            $code = $roles[$stored->slug] ?? null;

            if ($code !== null) {
                $roles[$stored->slug] = $code->locked ? $code : $this->applyChanges($code, $stored);
            } elseif (! $this->registry->isDeclared($stored->slug) && $stored->removed_capabilities === null) {
                // Changes to a plugin role whose plugin is off have "removed" set: they wait for the plugin.
                $custom[$stored->slug] = $this->custom($stored);
            }
        }

        uasort($custom, fn (RoleDefinition $a, RoleDefinition $b): int => strcasecmp($a->name, $b->name));

        return $this->cache = [...$roles, ...$custom];
    }

    public function find(string $slug): ?RoleDefinition
    {
        return $this->all()[$slug] ?? null;
    }

    public function exists(string $slug): bool
    {
        return isset($this->all()[$slug]);
    }

    /** @return list<string> */
    public function slugs(): array
    {
        return array_keys($this->all());
    }

    // The role as it is in code, before any change by an admin. Null for custom roles.
    public function defaults(string $slug): ?RoleDefinition
    {
        $role = Role::tryFrom($slug);

        return $role !== null ? $this->registry->builtIn($role) : ($this->registry->all()[$slug] ?? null);
    }

    // Forget the cached list after a role was saved or deleted.
    public function flush(): void
    {
        $this->cache = null;
    }

    protected function applyChanges(RoleDefinition $role, StoredRole $stored): RoleDefinition
    {
        $removed = $stored->removed_capabilities ?? [];
        $capabilities = array_values(array_unique([
            ...array_values(array_diff($role->capabilities, $removed)),
            ...$this->known($stored->capabilities),
        ]));

        return $role->with($stored->name, (string) $stored->description, $capabilities, true);
    }

    protected function custom(StoredRole $stored): RoleDefinition
    {
        return new RoleDefinition(
            slug: $stored->slug,
            name: $stored->name,
            description: (string) $stored->description,
            capabilities: $this->known($stored->capabilities),
            source: 'custom',
            basedOn: $stored->based_on,
        );
    }

    /**
     * Saved capabilities that still exist. Capabilities of a plugin that is off are kept in the
     * database, so they come back when the plugin is turned on again.
     *
     * @param  list<string>|null  $capabilities
     * @return list<string>
     */
    protected function known(?array $capabilities): array
    {
        return array_values(array_filter($capabilities ?? [], fn (string $capability): bool => $this->capabilities->has($capability)));
    }

    /** @return iterable<StoredRole> */
    protected function storedRoles(): iterable
    {
        // Before the migration runs (installer, first update) only the roles from code exist.
        // Check first: on Postgres a failed query breaks the open transaction.
        if (! Schema::hasTable('roles')) {
            return [];
        }

        return StoredRole::query()->orderBy('id')->get();
    }
}
