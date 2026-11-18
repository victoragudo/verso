<?php

namespace App\Verso\Editor\Builder;

// Base of every builder registry: items kept by a unique key, in the order they were added.
// Core, plugins and themes all add their items with register().
/**
 * @template TItem
 */
abstract class Registry
{
    // Version of the registry contract. It changes only when a change breaks plugins.
    public const int CONTRACT_VERSION = 1;

    /** @var array<string, TItem> */
    protected array $items = [];

    /** @param TItem $item */
    protected function put(string $key, mixed $item): void
    {
        $this->items[$key] = $item;
    }

    public function has(string $key): bool
    {
        return isset($this->items[$key]);
    }

    /** @return TItem|null */
    public function find(string $key): mixed
    {
        return $this->items[$key] ?? null;
    }

    // Removes an item, for example a core item a theme does not want.
    public function remove(string $key): void
    {
        unset($this->items[$key]);
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->items);
    }
}
