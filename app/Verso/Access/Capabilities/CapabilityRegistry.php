<?php

namespace App\Verso\Access\Capabilities;

use App\Verso\Access\Enums\EntryAction;
use App\Verso\Content\Admin\ContentLabels;
use App\Verso\Content\ContentType;
use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Core\Enums\Capability;
use InvalidArgumentException;

// Every capability in one place: core, content types, plugins and the active theme.
class CapabilityRegistry
{
    /** @var array<string, CapabilityDefinition> */
    protected array $definitions = [];

    protected ContentLabels $labels;

    public function __construct(protected ContentTypeRegistry $types)
    {
        $this->labels = new ContentLabels($types);

        foreach (CoreCapabilities::all() as $definition) {
            $this->definitions[$definition->key] = $definition;
        }
    }

    // Add a capability. A key can be declared once; core keys can never be replaced.
    public function register(CapabilityDefinition $definition): void
    {
        if (preg_match(CapabilityDefinition::KEY_PATTERN, $definition->key) !== 1 || EntryAction::parse($definition->key) !== null) {
            throw new InvalidArgumentException("The capability key [{$definition->key}] is not valid.");
        }

        $existing = $this->definitions[$definition->key] ?? null;

        if ($existing !== null && $existing->source !== $definition->source) {
            throw new InvalidArgumentException("The capability [{$definition->key}] is already declared by {$existing->source}.");
        }

        $this->definitions[$definition->key] = $definition;
    }

    // Remove the capabilities of a plugin or theme, for example when it is turned off in a test.
    public function forgetSource(string $source): void
    {
        $this->definitions = array_filter($this->definitions, fn (CapabilityDefinition $definition): bool => $definition->source !== $source);
    }

    public function has(string $key): bool
    {
        return isset($this->definitions[$key]) || $this->contentTypeCapability($key) !== null;
    }

    public function get(string $key): ?CapabilityDefinition
    {
        return $this->definitions[$key] ?? $this->contentTypeCapability($key);
    }

    /**
     * Every capability, sorted by group, then section, then the order they were declared.
     *
     * @return list<CapabilityDefinition>
     */
    public function all(): array
    {
        // Content type capabilities come right after the ones for all content types.
        $all = [];

        foreach ($this->definitions as $key => $definition) {
            $all[] = $definition;

            if ($key === Capability::ReadPrivateEntries->value) {
                array_push($all, ...$this->contentTypeCapabilities());
            }
        }

        $position = array_flip(array_map(fn (CapabilityDefinition $definition): string => $definition->key, $all));

        usort($all, fn (CapabilityDefinition $a, CapabilityDefinition $b): int => [CapabilityGroup::order($a->group), $a->group, $position[$a->key]]
            <=> [CapabilityGroup::order($b->group), $b->group, $position[$b->key]]);

        return $all;
    }

    /**
     * The label of every capability in the admin language, keyed by capability.
     *
     * @return array<string, string>
     */
    public function labels(): array
    {
        $labels = [];

        foreach ($this->all() as $definition) {
            $labels[$definition->key] = $definition->displayLabel();
        }

        return $labels;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_map(fn (CapabilityDefinition $definition): string => $definition->key, $this->all());
    }

    /**
     * The keys a capability gives, itself included, following "implies" all the way down.
     *
     * @return list<string>
     */
    public function expand(string $key): array
    {
        $found = [];
        $queue = [$key];

        while ($queue !== []) {
            $current = array_shift($queue);

            if (isset($found[$current])) {
                continue;
            }

            $found[$current] = true;
            array_push($queue, ...($this->definitions[$current]->implies ?? []));
        }

        return array_keys($found);
    }

    /**
     * Capabilities that plugins or the theme give to a built-in role by default.
     *
     * @return list<string>
     */
    public function defaultsFor(string $role): array
    {
        return array_values(array_map(
            fn (CapabilityDefinition $definition): string => $definition->key,
            array_filter($this->definitions, fn (CapabilityDefinition $definition): bool => in_array($role, $definition->roles, true)),
        ));
    }

    // True when the key belongs to the core enum (it can never be removed).
    public function isCore(string $key): bool
    {
        return Capability::tryFrom($key) !== null;
    }

    /** @return list<CapabilityDefinition> */
    protected function contentTypeCapabilities(): array
    {
        $definitions = [];

        foreach ($this->types->all() as $type) {
            foreach (EntryAction::cases() as $action) {
                $definitions[] = $this->entryDefinition($type, $action);
            }
        }

        return $definitions;
    }

    protected function contentTypeCapability(string $key): ?CapabilityDefinition
    {
        $parsed = EntryAction::parse($key);

        if ($parsed === null || ! $this->types->has($parsed[0])) {
            return null;
        }

        return $this->entryDefinition($this->types->get($parsed[0]), $parsed[1]);
    }

    protected function entryDefinition(ContentType $type, EntryAction $action): CapabilityDefinition
    {
        $plural = $this->labels->typePluralLabel($type);
        $inline = $this->labels->inline($plural);
        $plugin = $this->types->pluginOf($type->name);

        return new CapabilityDefinition(
            key: $action->capabilityFor($type->name),
            label: $action->label($inline),
            description: __('Only for :items. The "All content types" capability of the same kind also gives it.', ['items' => $inline]),
            group: CapabilityGroup::CONTENT,
            risk: $action->risk(),
            source: $plugin === null ? 'core' : 'plugin:'.$plugin,
            section: $plural,
        );
    }
}
