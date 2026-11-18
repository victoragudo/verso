<?php

namespace App\Verso\Easy\Styles;

use App\Verso\Easy\Support\PluginLabels;
use InvalidArgumentException;

// Keeps the font pairs of "Make it yours". Core registers its own, plugins and themes can add more.
class FontPairRegistry
{
    use PluginLabels;

    /** @var array<string, FontPair> */
    protected array $pairs = [];

    // Adds a font pair. One with the same key is replaced.
    public function register(FontPair $item, ?string $plugin = null): void
    {
        $this->pairs[$item->key] = $item;
        $this->rememberOwner($item->key, $plugin);
    }

    // A label of a registered item in the admin language.
    public function text(string $key, string $text): string
    {
        return $this->translated($key, $text);
    }

    public function has(string $key): bool
    {
        return isset($this->pairs[$key]);
    }

    public function get(string $key): FontPair
    {
        return $this->pairs[$key] ?? throw new InvalidArgumentException("Unknown font pair [{$key}].");
    }

    public function find(string $key): ?FontPair
    {
        return $this->pairs[$key] ?? null;
    }

    /**
     * Every font pair, sorted by order, then by label.
     *
     * @return array<string, FontPair>
     */
    public function all(): array
    {
        return self::sorted($this->pairs);
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    /**
     * Sorts font pairs by order, then by label.
     *
     * @param  array<string, FontPair>  $items
     * @return array<string, FontPair>
     */
    public static function sorted(array $items): array
    {
        uasort($items, fn (FontPair $a, FontPair $b): int => [$a->order, $a->label] <=> [$b->order, $b->label]);

        return $items;
    }
}
