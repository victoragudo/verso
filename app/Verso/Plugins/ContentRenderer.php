<?php

namespace App\Verso\Plugins;

use App\Verso\Content\Models\Entry;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Editor\Support\RenderContext;
use App\Verso\Plugins\Blocks\PluginBlockRegistry;
use App\Verso\Plugins\Contracts\CacheableBlock;
use App\Verso\Plugins\Contracts\RendersWithRenderer;
use App\Verso\Plugins\Filters\BlockHtmlFilter;
use App\Verso\Plugins\Filters\EntryContentFilter;
use App\Verso\Shortcodes\Parsing\ShortcodeLimits;
use App\Verso\Shortcodes\Parsing\ShortcodeParser;

// Renders the HTML of an entry: core blocks, plugin blocks, then the EntryContentFilter.
class ContentRenderer
{
    public function __construct(
        protected BlockRenderer $blocks,
        protected PluginBlockRegistry $pluginBlocks,
        protected ?BlockOutputCache $cache = null,
    ) {}

    // Entries with only core blocks come from the block cache. Edit mode output is never cached. Plugin blocks may depend on the visitor, so they are never cached.
    // Shortcodes render plugin blocks (and warnings for admins), so entries with shortcodes are rendered fresh too.
    public function render(Entry $entry): string
    {
        $blocks = $entry->blocks ?? [];
        $render = fn (): string => app(RenderContext::class)->withEntry($entry, fn (): string => $this->blocks->withStyles(function () use ($blocks, $entry): string {
            $html = '';

            foreach ($blocks as $block) {
                $html .= $this->renderBlock($block, $entry);
            }

            return $html;
        }));

        $html = $this->cache !== null && ! $this->blocks->showsMarkers() && ! $this->hasPluginBlocks($blocks) && ! $this->hasShortcodes($blocks)
            ? $this->cache->remember($entry, $render)
            : $render();

        if ($blocks === [] && $this->blocks->showsMarkers()) {
            $html = '<div data-verso-empty></div>';
        }

        return EntryContentFilter::apply($html, $entry);
    }

    /**
     * True when a plugin renders any block, at any depth.
     *
     * @param  array<mixed>  $blocks
     */
    public function hasPluginBlocks(array $blocks): bool
    {
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $plugin = is_string($block['type'] ?? null) ? $this->pluginBlocks->find($block['type']) : null;

            if ($plugin !== null && ! $plugin instanceof CacheableBlock) {
                return true;
            }

            if (is_array($block['children'] ?? null) && $this->hasPluginBlocks($block['children'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * True when any text of any block holds something that looks like a shortcode.
     *
     * @param  array<mixed>  $blocks
     */
    public function hasShortcodes(array $blocks): bool
    {
        if (! config('verso.shortcodes.enabled')) {
            return false;
        }

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            if (is_string($block['content'] ?? null) && $this->shortcodeParser()->contains($block['content'])) {
                return true;
            }

            if (is_array($block['children'] ?? null) && $this->hasShortcodes($block['children'])) {
                return true;
            }
        }

        return false;
    }

    protected function shortcodeParser(): ShortcodeParser
    {
        return new ShortcodeParser(ShortcodeLimits::fromConfig());
    }

    /**
     * Renders the children of a block, for plugin blocks that hold other blocks.
     *
     * @param  array<mixed>  $block
     */
    public function renderChildren(array $block, ?Entry $entry = null): string
    {
        $html = '';

        foreach ((array) ($block['children'] ?? []) as $child) {
            if (is_array($child)) {
                $html .= $this->renderBlock($child, $entry);
            }
        }

        return $html;
    }

    /**
     * Plugin blocks are rendered by their plugin (then get their style class, like nested ones), all others by the core renderer.
     * Then the BlockHtmlFilter can change the HTML of the block.
     *
     * @param  array<mixed>  $block
     */
    public function renderBlock(array $block, ?Entry $entry = null): string
    {
        $plugin = is_string($block['type'] ?? null) ? $this->pluginBlocks->find($block['type']) : null;

        if ($plugin instanceof RendersWithRenderer) {
            $html = $this->blocks->renderBlock($block);
        } elseif ($plugin !== null) {
            $data = BlockData::from($block);
            $html = $this->blocks->skips($data) ? '' : $this->blocks->safely($data, fn (): string => $this->blocks->decorate($plugin->render($block, $entry), $data));
        } else {
            $html = $this->blocks->renderBlock($block);
        }

        return BlockHtmlFilter::apply($html, $block, $entry);
    }
}
