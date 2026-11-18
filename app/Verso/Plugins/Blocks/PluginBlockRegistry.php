<?php

namespace App\Verso\Plugins\Blocks;

use App\Verso\I18n\Translate;
use App\Verso\Plugins\Contracts\EmbeddableBlock;
use App\Verso\Plugins\Contracts\HasBlockCategory;
use App\Verso\Plugins\Contracts\HasBlockFields;
use App\Verso\Plugins\Contracts\HasChildBlocks;
use App\Verso\Plugins\Contracts\HasParentBlocks;
use App\Verso\Plugins\Contracts\HasStarterBlocks;
use App\Verso\Plugins\Contracts\PluginBlock;
use App\Verso\Plugins\Contracts\RestrictsChildBlocks;

// Keeps the block types that plugins add.
class PluginBlockRegistry
{
    /** @var array<string, PluginBlock> */
    protected array $blocks = [];

    /** @var array<string, string> block name => plugin slug */
    protected array $plugins = [];

    public function register(PluginBlock $block, string $plugin = ''): void
    {
        $this->blocks[$block->name()] = $block;
        $this->plugins[$block->name()] = $plugin;
    }

    // Slug of the plugin that added the block, or an empty text.
    public function pluginOf(string $name): string
    {
        return $this->plugins[$name] ?? '';
    }

    public function has(string $name): bool
    {
        return isset($this->blocks[$name]);
    }

    public function find(string $name): ?PluginBlock
    {
        return $this->blocks[$name] ?? null;
    }

    /**
     * @return array<string, PluginBlock>
     */
    public function all(): array
    {
        return $this->blocks;
    }

    /**
     * Name, label, description, fields and children flag of each block, for the editor.
     *
     * The shortcode flag and attr aliases let the editor turn shortcodes into blocks and chips.
     *
     * @return list<array{name: string, label: string, description: string, fields: list<array<string, mixed>>, children: bool, childTypes: list<string>|null, parents: list<string>|null, starter: list<array<string, mixed>>, shortcode: bool, aliases: array<string, string>, category?: string}>
     */
    public function toArray(): array
    {
        return array_values(array_map(
            fn (PluginBlock $block): array => [
                'name' => $block->name(),
                'label' => $this->text($block->label(), $block->name()),
                'description' => $block instanceof HasBlockFields ? $this->text($block->description(), $block->name()) : '',
                'fields' => $block instanceof HasBlockFields
                    ? array_map(fn (BlockField $field): array => $field->toArray($this->pluginOf($block->name())), $block->fields())
                    : [],
                'children' => $block instanceof HasChildBlocks,
                'childTypes' => $block instanceof RestrictsChildBlocks ? $block->childTypes() : null,
                'parents' => $block instanceof HasParentBlocks ? $block->parents() : null,
                'starter' => $block instanceof HasStarterBlocks ? $block->starter() : [],
                'shortcode' => $block instanceof HasBlockFields && (! $block instanceof EmbeddableBlock || $block->shortcode()),
                'aliases' => $block instanceof EmbeddableBlock ? $block->attributeAliases() : [],
                ...($block instanceof HasBlockCategory ? ['category' => $block->category()] : []),
            ],
            $this->blocks,
        ));
    }

    protected function text(string $text, string $name): string
    {
        $plugin = $this->pluginOf($name);

        return $plugin === '' || $text === '' ? $text : Translate::text($text, plugin: $plugin);
    }
}
