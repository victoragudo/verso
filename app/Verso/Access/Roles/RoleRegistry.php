<?php

namespace App\Verso\Access\Roles;

use App\Verso\Access\Capabilities\CapabilityRegistry;
use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Enums\Role;
use App\Verso\Core\Support\OptionalRoles;
use InvalidArgumentException;

// Roles declared in code: the built-in roles and the roles of enabled plugins, with their default capabilities.
class RoleRegistry
{
    /** @var array<string, RoleDefinition> */
    protected array $pluginRoles = [];

    public function __construct(
        protected CapabilityRegistry $capabilities,
        protected OptionalRoles $optionalRoles,
    ) {}

    // Add a role from a plugin or theme. Built-in role slugs cannot be used.
    public function register(RoleDefinition $role): void
    {
        if (Role::tryFrom($role->slug) !== null || preg_match(RoleDefinition::SLUG_PATTERN, $role->slug) !== 1) {
            throw new InvalidArgumentException("The role slug [{$role->slug}] cannot be used.");
        }

        $existing = $this->pluginRoles[$role->slug] ?? null;

        if ($existing !== null && $existing->source !== $role->source) {
            throw new InvalidArgumentException("The role [{$role->slug}] is already declared by {$existing->source}.");
        }

        $this->pluginRoles[$role->slug] = $role;
    }

    public function forgetSource(string $source): void
    {
        $this->pluginRoles = array_filter($this->pluginRoles, fn (RoleDefinition $role): bool => $role->source !== $source);
    }

    /**
     * Roles from code that can be used now, with their default capabilities.
     *
     * @return array<string, RoleDefinition>
     */
    public function all(): array
    {
        $roles = [];

        foreach ($this->optionalRoles->available() as $role) {
            $roles[$role->value] = $this->builtIn($role);
        }

        foreach ($this->pluginRoles as $slug => $role) {
            $roles[$slug] = $role;
        }

        return $roles;
    }

    // True when the slug is declared in code (a built-in role or a plugin role), even when it is not available.
    public function isDeclared(string $slug): bool
    {
        return Role::tryFrom($slug) !== null || isset($this->pluginRoles[$slug]);
    }

    public function builtIn(Role $role): RoleDefinition
    {
        $capabilities = $role === Role::Admin
            ? $this->capabilities->keys()
            : array_values(array_unique([
                ...array_map(fn (Capability $capability): string => $capability->value, $role->capabilities()),
                ...$this->capabilities->defaultsFor($role->value),
            ]));

        return new RoleDefinition(
            slug: $role->value,
            name: $role->label(),
            description: $role->description(),
            capabilities: $capabilities,
            source: $role === Role::ShopManager ? 'plugin:shop' : 'core',
            builtIn: true,
            locked: $role === Role::Admin,
        );
    }
}
