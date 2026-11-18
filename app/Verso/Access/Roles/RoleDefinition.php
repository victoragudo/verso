<?php

namespace App\Verso\Access\Roles;

use App\Verso\Access\Support\TranslatesBySource;

// One role as people see it: a name and a list of capabilities.
final readonly class RoleDefinition
{
    use TranslatesBySource;

    // Role slugs are lowercase words joined by underscores or dashes, like "seo_manager".
    public const SLUG_PATTERN = '/^[a-z][a-z0-9]*(?:[_\-][a-z0-9]+)*$/';

    /**
     * @param  list<string>  $capabilities
     */
    public function __construct(
        public string $slug,
        public string $name,
        public string $description = '',
        public array $capabilities = [],
        public string $source = 'custom',
        public bool $builtIn = false,
        public bool $locked = false,
        public bool $modified = false,
        public ?string $basedOn = null,
    ) {}

    public function has(string $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }

    public function displayName(): string
    {
        return $this->builtIn ? $this->translateBySource($this->name) : $this->name;
    }

    public function displayDescription(): string
    {
        return $this->builtIn ? $this->translateBySource($this->description) : $this->description;
    }

    /**
     * @return array{slug: string, name: string, description: string, capabilities: list<string>, source: string, builtIn: bool, locked: bool, modified: bool, basedOn: string|null}
     */
    public function present(): array
    {
        return [
            ...$this->toArray(),
            'name' => $this->displayName(),
            'description' => $this->displayDescription(),
        ];
    }

    /**
     * @param  list<string>  $capabilities
     */
    public function with(string $name, string $description, array $capabilities, bool $modified): self
    {
        return new self($this->slug, $name, $description, $capabilities, $this->source, $this->builtIn, $this->locked, $modified, $this->basedOn);
    }

    /**
     * Data for the admin UI.
     *
     * @return array{slug: string, name: string, description: string, capabilities: list<string>, source: string, builtIn: bool, locked: bool, modified: bool, basedOn: string|null}
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'capabilities' => $this->capabilities,
            'source' => $this->source,
            'builtIn' => $this->builtIn,
            'locked' => $this->locked,
            'modified' => $this->modified,
            'basedOn' => $this->basedOn,
        ];
    }
}
