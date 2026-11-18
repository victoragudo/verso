<?php

namespace App\Verso\Easy\Launch;

use App\Verso\Easy\Support\PluginLabels;
use InvalidArgumentException;

// Keeps the steps of the launch checklist on the dashboard. Core registers its own, plugins can add more.
class LaunchChecklist
{
    use PluginLabels;

    /** @var array<string, LaunchCheck> */
    protected array $checks = [];

    // Adds a step. One with the same key is replaced.
    public function register(LaunchCheck $check, ?string $plugin = null): void
    {
        $this->checks[$check->key] = $check;
        $this->rememberOwner($check->key, $plugin);
    }

    public function unregister(string $key): void
    {
        unset($this->checks[$key], $this->owners[$key]);
    }

    public function has(string $key): bool
    {
        return isset($this->checks[$key]);
    }

    public function get(string $key): LaunchCheck
    {
        return $this->checks[$key] ?? throw new InvalidArgumentException("Unknown launch check [{$key}].");
    }

    public function find(string $key): ?LaunchCheck
    {
        return $this->checks[$key] ?? null;
    }

    /**
     * Every step that applies to this site, sorted by order, then by label.
     *
     * @return list<LaunchCheck>
     */
    public function all(): array
    {
        $checks = array_values(array_filter($this->checks, fn (LaunchCheck $check): bool => $check->applies()));

        usort($checks, fn (LaunchCheck $a, LaunchCheck $b): int => [$a->order, $a->label] <=> [$b->order, $b->label]);

        return $checks;
    }

    /**
     * The rows with their state, and how many are done.
     *
     * @return array{items: list<array{key: string, label: string, description: string, href: string, action: string, method: string, done: bool}>, done: int, total: int}
     */
    public function toArray(): array
    {
        $items = array_map(fn (LaunchCheck $check): array => [
            ...$check->toArray(),
            'label' => $this->translated($check->key, $check->label),
            'description' => $this->translated($check->key, $check->description),
            'action' => $check->action === null ? __('Do it') : $this->translated($check->key, $check->action),
        ], $this->all());

        return [
            'items' => $items,
            'done' => count(array_filter($items, fn (array $item): bool => $item['done'])),
            'total' => count($items),
        ];
    }

    public function isComplete(): bool
    {
        foreach ($this->all() as $check) {
            if (! $check->isDone()) {
                return false;
            }
        }

        return true;
    }
}
