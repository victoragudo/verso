<?php

namespace App\Verso\Shortcodes;

use App\Verso\Plugins\Blocks\PluginBlockRegistry;
use App\Verso\Plugins\Contracts\EmbeddableBlock;
use App\Verso\Plugins\Contracts\HasBlockFields;

// Every plugin block with fields is also a shortcode and a Blade embed, unless it opts out.
// It reads the plugin block registry, so there is only one list of embeddable components.
class ShortcodeRegistry
{
    public function __construct(protected PluginBlockRegistry $blocks) {}

    public function find(string $name): ?ShortcodeDefinition
    {
        $block = $this->blocks->find($name);

        if (! $block instanceof HasBlockFields || ($block instanceof EmbeddableBlock && ! $block->shortcode())) {
            return null;
        }

        return new ShortcodeDefinition($block, $this->blocks->pluginOf($name));
    }

    public function has(string $name): bool
    {
        return $this->find($name) !== null;
    }

    /**
     * @return array<string, ShortcodeDefinition>
     */
    public function all(): array
    {
        $definitions = [];

        foreach (array_keys($this->blocks->all()) as $name) {
            $definition = $this->find($name);

            if ($definition !== null) {
                $definitions[$name] = $definition;
            }
        }

        ksort($definitions);

        return $definitions;
    }

    // True when the shortcode wraps inner text, like [box]...[/box].
    public function enclosing(string $name): bool
    {
        return $this->find($name)?->enclosing() ?? false;
    }

    /**
     * The shortcode text for an item, like [form slug="contact"]. Quotes in values become &quot;.
     *
     * @param  array<string, scalar>  $attrs
     */
    public function snippet(string $name, array $attrs = []): string
    {
        $parts = array_map(fn (string $key, mixed $value): string => $key.'="'.str_replace('"', '&quot;', (string) $value).'"', array_keys($attrs), $attrs);

        return '['.implode(' ', [$name, ...$parts]).']';
    }

    /**
     * The Blade embed for an item, like <x-verso-embed name="form" :attrs="['slug' => 'contact']" />. Values are PHP strings in single quotes.
     *
     * @param  array<string, scalar>  $attrs
     */
    public function bladeSnippet(string $name, array $attrs = []): string
    {
        $parts = array_map(fn (string $key, mixed $value): string => var_export($key, true).' => '.var_export($value, true), array_keys($attrs), $attrs);

        return '<x-verso-embed name="'.e($name).'"'.($parts === [] ? '' : ' :attrs="['.implode(', ', $parts).']"').' />';
    }

    /**
     * Name, label and field names of each shortcode, for the editor.
     *
     * @return list<array{name: string, label: string, enclosing: bool, aliases: array<string, string>}>
     */
    public function toArray(): array
    {
        return array_values(array_map(fn (ShortcodeDefinition $definition): array => [
            'name' => $definition->name(),
            'label' => $definition->label(),
            'enclosing' => $definition->enclosing(),
            'aliases' => $definition->aliases(),
        ], $this->all()));
    }
}
