<?php

namespace App\Verso\Content;

use InvalidArgumentException;

// Keeps every taxonomy. Core registers "category" and "tag".
class TaxonomyRegistry
{
    /** @var array<string, Taxonomy> */
    protected array $taxonomies = [];

    public function register(Taxonomy $taxonomy): void
    {
        $this->taxonomies[$taxonomy->name] = $taxonomy;
    }

    public function has(string $name): bool
    {
        return isset($this->taxonomies[$name]);
    }

    public function get(string $name): Taxonomy
    {
        return $this->taxonomies[$name] ?? throw new InvalidArgumentException("Unknown taxonomy [{$name}].");
    }

    public function find(string $name): ?Taxonomy
    {
        return $this->taxonomies[$name] ?? null;
    }

    /**
     * @return array<string, Taxonomy>
     */
    public function all(): array
    {
        $taxonomies = $this->taxonomies;

        uasort($taxonomies, fn (Taxonomy $a, Taxonomy $b): int => $a->menuOrder <=> $b->menuOrder);

        return $taxonomies;
    }

    /**
     * The taxonomies that can be used on a content type.
     *
     * @return array<string, Taxonomy>
     */
    public function forContentType(string $contentType): array
    {
        return array_filter($this->all(), fn (Taxonomy $taxonomy): bool => $taxonomy->appliesTo($contentType));
    }
}
