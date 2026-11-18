<?php

namespace App\Verso\Blocks\Support;

use App\Verso\Content\Models\Entry;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Icons\IconLibrary;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Editor\Support\UrlSanitizer;
use App\Verso\Plugins\Contracts\EmbeddableBlock;
use App\Verso\Plugins\Contracts\HasBlockCategory;
use App\Verso\Plugins\Contracts\HasBlockFields;
use App\Verso\Plugins\Contracts\PluginBlock;
use App\Verso\Plugins\Contracts\RendersWithRenderer;

// Base of the core builder blocks (tabs, slider, counter...). Each block is one class with its fields and HTML,
// plus optional CSS and JavaScript in resources/blocks/<name>, loaded only on pages that show the block.
abstract class ModuleBlock implements EmbeddableBlock, HasBlockCategory, HasBlockFields, PluginBlock, RendersWithRenderer
{
    // The block type, like "tabs".
    abstract public function name(): string;

    // The safe HTML of the block.
    abstract protected function html(BlockData $block, BlockRenderer $renderer): string;

    public function category(): string
    {
        return 'interactive';
    }

    public function fields(): array
    {
        return [];
    }

    // Folders with the CSS and JavaScript of the block, by asset key. Most blocks have one, named like the block.
    /** @return list<string> */
    public function assets(): array
    {
        return [$this->name()];
    }

    public function renderWith(BlockData $block, BlockRenderer $renderer): string
    {
        return $this->html($block, $renderer);
    }

    // Shortcodes and Blade embeds draw the block with the same renderer.
    public function render(array $block, ?Entry $entry = null): string
    {
        return app(BlockRenderer::class)->withStyles(fn (): string => app(BlockRenderer::class)->renderBlock($block));
    }

    // Builder blocks are blocks only: their shortcode names stay free for plugins.
    public function shortcode(): bool
    {
        return false;
    }

    public function keyAttribute(): ?string
    {
        return null;
    }

    public function attributeAliases(): array
    {
        return [];
    }

    public function capability(): ?string
    {
        return null;
    }

    // A stable id for HTML ids inside the block, like "vb-b_k2j3-panel-1".
    protected function domId(BlockData $block, string $suffix = ''): string
    {
        $id = 'vb-'.(preg_replace('/[^A-Za-z0-9_-]/', '', $block->id) ?: substr(md5(json_encode($block->attrs) ?: ''), 0, 8));

        return $suffix === '' ? $id : $id.'-'.$suffix;
    }

    protected function text(BlockData $block, string $key, string $default = ''): string
    {
        $value = $block->attrs[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : $default;
    }

    protected function number(BlockData $block, string $key, float $default, float $min, float $max): float
    {
        $value = $block->attrs[$key] ?? null;

        return is_numeric($value) ? max($min, min($max, (float) $value)) : $default;
    }

    /** @param list<string> $allowed */
    protected function choice(BlockData $block, string $key, array $allowed, string $default): string
    {
        $value = $block->attrs[$key] ?? null;

        return is_string($value) && in_array($value, $allowed, true) ? $value : $default;
    }

    // Inline marks only (bold, links...), cleaned by the editor sanitizer.
    protected function inline(BlockRenderer $renderer, string $html): string
    {
        return trim($renderer->sanitizeInline($html));
    }

    protected function url(BlockData $block, string $key): ?string
    {
        $value = $block->attrs[$key] ?? null;

        return is_string($value) && $value !== '' ? app(UrlSanitizer::class)->clean($value) : null;
    }

    protected function icon(string $name, string $label = '', string $class = 'verso-icon'): string
    {
        return $name === '' ? '' : app(IconLibrary::class)->svg($name, $label, $class);
    }

    /**
     * A picture field: {id, url, alt, width, height}. Null when it is empty or its address is not safe.
     *
     * @return array{id: int|null, url: string, alt: string, width: int|null, height: int|null}|null
     */
    protected function media(BlockData $block, string $key): ?array
    {
        $value = $block->attrs[$key] ?? null;

        if (! is_array($value)) {
            return null;
        }

        $url = is_string($value['url'] ?? null) ? app(UrlSanitizer::class)->clean($value['url']) : null;

        if ($url === null) {
            return null;
        }

        return [
            'id' => is_int($value['id'] ?? null) ? $value['id'] : null,
            'url' => $url,
            'alt' => is_string($value['alt'] ?? null) ? $value['alt'] : '',
            'width' => is_int($value['width'] ?? null) ? $value['width'] : null,
            'height' => is_int($value['height'] ?? null) ? $value['height'] : null,
        ];
    }

    /** @param array{url: string, alt: string, width: int|null, height: int|null} $media */
    protected function img(array $media, string $class = '', bool $lazy = true, ?string $alt = null): string
    {
        return '<img src="'.e($media['url']).'" alt="'.e($alt ?? $media['alt']).'"'
            .($class !== '' ? ' class="'.e($class).'"' : '')
            .($media['width'] !== null ? ' width="'.$media['width'].'"' : '')
            .($media['height'] !== null ? ' height="'.$media['height'].'"' : '')
            .($lazy ? ' loading="lazy"' : '').' decoding="async">';
    }

    /**
     * The children of the block that are of one type.
     *
     * @return list<BlockData>
     */
    protected function childrenOf(BlockData $block, string $type): array
    {
        $children = [];

        foreach ($block->children as $child) {
            if (($child['type'] ?? null) === $type) {
                $children[] = BlockData::from($child, $block->depth + 1);
            }
        }

        return $children;
    }

    // A copy of a block with one more attr, like the place of a tab, for the child to read while it is drawn.
    protected function withAttr(BlockData $block, string $key, mixed $value): BlockData
    {
        return new BlockData($block->id, $block->type, [...$block->attrs, $key => $value], $block->content, $block->children, $block->depth);
    }

    // One child block drawn with its own style class, like a single tab panel.
    protected function child(BlockRenderer $renderer, BlockData $child): string
    {
        return $renderer->renderBlock(['id' => $child->id, 'type' => $child->type, 'attrs' => $child->attrs, 'content' => $child->content, 'children' => $child->children], $child->depth);
    }
}
