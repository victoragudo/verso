<?php

namespace App\Verso\Easy\Starters;

use App\Verso\Easy\Support\PluginLabels;
use InvalidArgumentException;

// Keeps the starter sites of the guided start. Core registers seven, plugins and themes can add more.
class StarterSiteRegistry
{
    use PluginLabels;

    /** @var array<string, StarterSite> */
    protected array $sites = [];

    // Adds a starter site. One with the same key is replaced. Plugins pass their slug to translate the labels.
    public function register(StarterSite $site, ?string $plugin = null): void
    {
        $this->sites[$site->key] = $site;
        $this->rememberOwner($site->key, $plugin);
    }

    // The name of a starter site in the admin language.
    public function label(StarterSite $site): string
    {
        return $this->translated($site->key, $site->label);
    }

    public function has(string $key): bool
    {
        return isset($this->sites[$key]);
    }

    public function get(string $key): StarterSite
    {
        return $this->sites[$key] ?? throw new InvalidArgumentException("Unknown starter site [{$key}].");
    }

    public function find(string $key): ?StarterSite
    {
        return $this->sites[$key] ?? null;
    }

    /**
     * Every starter site, sorted by order, then by label.
     *
     * @return array<string, StarterSite>
     */
    public function all(): array
    {
        $sites = $this->sites;
        uasort($sites, fn (StarterSite $a, StarterSite $b): int => [$a->order, $a->label] <=> [$b->order, $b->label]);

        return $sites;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_values(array_map(fn (StarterSite $site): array => [
            ...$site->toArray(),
            'label' => $this->label($site),
            'description' => $this->translated($site->key, $site->description),
        ], $this->all()));
    }
}
