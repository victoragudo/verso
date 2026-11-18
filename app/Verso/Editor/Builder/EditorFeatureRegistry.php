<?php

namespace App\Verso\Editor\Builder;

/**
 * Keeps the editor features that the "Editor settings" page can turn on or off.
 *
 * @extends Registry<EditorFeature>
 */
class EditorFeatureRegistry extends Registry
{
    public function register(EditorFeature $feature): void
    {
        $this->put($feature->key, $feature);
    }

    /** @return list<EditorFeature> */
    public function all(): array
    {
        return array_values($this->items);
    }

    /** @return list<array{key: string, label: string, description: string, roles: list<string>}> */
    public function toArray(): array
    {
        return array_map(fn (EditorFeature $feature): array => $feature->toArray(), $this->all());
    }
}
