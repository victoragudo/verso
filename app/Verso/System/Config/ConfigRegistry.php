<?php

namespace App\Verso\System\Config;

use Closure;
use InvalidArgumentException;

// Holds every system setting and group. Core and plugins add theirs through the same methods.
class ConfigRegistry
{
    /** @var array<string, ConfigGroup> */
    protected array $groups = [];

    /** @var array<string, ConfigParameter> */
    protected array $parameters = [];

    /** @var list<Closure(list<ConfigParameter>): void> */
    protected array $listeners = [];

    public function addGroup(ConfigGroup $group): void
    {
        $this->groups[$group->key] = $group;
    }

    // Add settings. A key can only be declared once.
    public function add(ConfigParameter ...$parameters): void
    {
        $added = [];

        foreach ($parameters as $parameter) {
            if (isset($this->parameters[$parameter->key])) {
                throw new InvalidArgumentException("The system setting [{$parameter->key}] is declared twice.");
            }

            $this->parameters[$parameter->key] = $parameter;
            $added[] = $parameter;
        }

        foreach ($this->listeners as $listener) {
            $listener($added);
        }
    }

    // Call the closure for settings added from now on (the overlay uses it for late plugins).
    /**
     * @param  Closure(list<ConfigParameter>): void  $listener
     */
    public function onAdd(Closure $listener): void
    {
        $this->listeners[] = $listener;
    }

    public function find(string $key): ?ConfigParameter
    {
        return $this->parameters[$key] ?? null;
    }

    /** @return list<ConfigParameter> */
    public function all(): array
    {
        return array_values($this->parameters);
    }

    /** @return list<ConfigGroup> groups with at least one setting, in order */
    public function groups(): array
    {
        $used = array_flip(array_map(fn (ConfigParameter $parameter): string => $parameter->group, $this->parameters));
        $groups = array_values(array_filter($this->groups, fn (ConfigGroup $group): bool => isset($used[$group->key])));
        usort($groups, fn (ConfigGroup $a, ConfigGroup $b): int => [$a->order, $a->label] <=> [$b->order, $b->label]);

        return $groups;
    }
}
