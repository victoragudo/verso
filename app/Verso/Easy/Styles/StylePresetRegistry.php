<?php

namespace App\Verso\Easy\Styles;

use App\Verso\Easy\Support\PluginLabels;
use InvalidArgumentException;

// Keeps the style presets of "Make it yours". Core registers its own, plugins and themes can add more.
class StylePresetRegistry
{
    use PluginLabels;

    /** @var array<string, StylePreset> */
    protected array $presets = [];

    // Adds a style preset. One with the same key is replaced.
    public function register(StylePreset $item, ?string $plugin = null): void
    {
        $this->presets[$item->key] = $item;
        $this->rememberOwner($item->key, $plugin);
    }

    // A label of a registered item in the admin language.
    public function text(string $key, string $text): string
    {
        return $this->translated($key, $text);
    }

    public function has(string $key): bool
    {
        return isset($this->presets[$key]);
    }

    public function get(string $key): StylePreset
    {
        return $this->presets[$key] ?? throw new InvalidArgumentException("Unknown style preset [{$key}].");
    }

    public function find(string $key): ?StylePreset
    {
        return $this->presets[$key] ?? null;
    }

    /**
     * Every style preset, sorted by order, then by label.
     *
     * @return array<string, StylePreset>
     */
    public function all(): array
    {
        return self::sorted($this->presets);
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    /**
     * Sorts style presets by order, then by label.
     *
     * @param  array<string, StylePreset>  $items
     * @return array<string, StylePreset>
     */
    public static function sorted(array $items): array
    {
        uasort($items, fn (StylePreset $a, StylePreset $b): int => [$a->order, $a->label] <=> [$b->order, $b->label]);

        return $items;
    }
}
