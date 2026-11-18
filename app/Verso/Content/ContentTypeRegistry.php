<?php

namespace App\Verso\Content;

use InvalidArgumentException;

// Keeps every content type. Core registers "post" and "page". Plugins can add more.
class ContentTypeRegistry
{
    /** @var array<string, ContentType> */
    protected array $types = [];

    /** @var array<string, string> content type name => slug of the plugin that added it */
    protected array $plugins = [];

    // Add a type. $plugin is the slug of the plugin that adds it, or null for core types.
    public function register(ContentType $type, ?string $plugin = null): void
    {
        $this->types[$type->name] = $type;

        if ($plugin === null) {
            unset($this->plugins[$type->name]);
        } else {
            $this->plugins[$type->name] = $plugin;
        }
    }

    // The slug of the plugin that added the type, or null for a core type.
    public function pluginOf(string $name): ?string
    {
        return $this->plugins[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->types[$name]);
    }

    public function get(string $name): ContentType
    {
        return $this->types[$name] ?? throw new InvalidArgumentException("Unknown content type [{$name}].");
    }

    public function find(string $name): ?ContentType
    {
        return $this->types[$name] ?? null;
    }

    /**
     * @return array<string, ContentType>
     */
    public function all(): array
    {
        $types = $this->types;

        uasort($types, fn (ContentType $a, ContentType $b): int => $a->menuOrder <=> $b->menuOrder);

        return $types;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->all());
    }
}
