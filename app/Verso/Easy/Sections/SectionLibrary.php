<?php

namespace App\Verso\Easy\Sections;

use App\Verso\Easy\Support\PluginLabels;
use App\Verso\Plugins\PluginManager;
use InvalidArgumentException;

// Keeps the ready sections. Core registers its own, plugins and themes add theirs in their provider boot().
class SectionLibrary
{
    use PluginLabels;

    /** @var array<string, SectionTemplate> */
    protected array $sections = [];

    public function __construct(protected ?PluginManager $plugins = null) {}

    // Adds a section. A section with the same key is replaced.
    public function register(SectionTemplate $section, ?string $plugin = null): void
    {
        $this->sections[$section->key] = $section;
        $this->rememberOwner($section->key, $plugin);
    }

    public function has(string $key): bool
    {
        return isset($this->sections[$key]);
    }

    public function get(string $key): SectionTemplate
    {
        return $this->sections[$key] ?? throw new InvalidArgumentException("Unknown section [{$key}].");
    }

    public function find(string $key): ?SectionTemplate
    {
        return $this->sections[$key] ?? null;
    }

    /**
     * @return array<string, SectionTemplate>
     */
    public function all(): array
    {
        return $this->sections;
    }

    /**
     * Sections that can be used now: their plugin, when they need one, is turned on.
     *
     * @return array<string, SectionTemplate>
     */
    public function available(): array
    {
        return array_filter($this->sections, $this->isAvailable(...));
    }

    public function isAvailable(SectionTemplate $section): bool
    {
        return $section->plugin === null || ($this->plugins?->isEnabled($section->plugin) ?? false);
    }

    /**
     * The blocks of one section, built with the given kit (or a new one).
     *
     * @param  SectionContent|array<string, mixed>  $content
     * @return list<array<string, mixed>>
     */
    public function build(string $key, SectionContent|array $content = [], ?SectionKit $kit = null): array
    {
        return $this->get($key)->build($content, $kit);
    }

    /**
     * The available sections for the editor gallery.
     *
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_values(array_map(fn (SectionTemplate $section): array => [
            ...$section->toArray(),
            'label' => $this->translated($section->key, $section->label, $section->plugin),
            'description' => $this->translated($section->key, $section->description, $section->plugin),
            'keywords' => $this->translatedKeywords($section->key, $section->keywords, $section->plugin),
        ], $this->available()));
    }
}
