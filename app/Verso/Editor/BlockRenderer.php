<?php

namespace App\Verso\Editor;

use App\Verso\Editor\Assets\BlockAssetRegistry;
use App\Verso\Editor\Builder\DropRuleRegistry;
use App\Verso\Editor\Contracts\InlineExpander;
use App\Verso\Editor\Styles\BlockStyleCompiler;
use App\Verso\Editor\Styles\BlockStyleSchema;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Editor\Support\HtmlSanitizer;
use App\Verso\Editor\Support\UrlSanitizer;
use Closure;
use Illuminate\Support\Arr;
use Throwable;

// Turns entry blocks into safe HTML using the renderers in the block registry.
class BlockRenderer
{
    protected ?InlineExpander $expander = null;

    // How many render() calls are running. Styles are printed by the outermost one.
    protected int $rendering = 0;

    /** @var array<string, string> CSS of the styled blocks of the running render, by class */
    protected array $collected = [];

    /** @var array<string, true> asset keys the blocks of the running render need */
    protected array $assetKeys = [];

    // Edit mode: every block tells the visual editor its id and type, and hidden blocks stay on screen.
    protected bool $markers = false;

    // Pattern of the first HTML tag of a block, with its attributes.
    protected const FIRST_TAG = '/^(\s*<[a-z][a-z0-9-]*)(\s[^>]*)?>/i';

    public function __construct(
        protected BlockRegistry $registry,
        protected HtmlSanitizer $sanitizer,
        protected UrlSanitizer $urls,
        protected int $maxDepth,
        protected ?BlockStyleCompiler $styles = null,
        protected ?DropRuleRegistry $dropRules = null,
        protected ?BlockAssetRegistry $assets = null,
    ) {}

    /**
     * @param  array<int, mixed>  $blocks
     */
    public function render(array $blocks): string
    {
        return $this->withStyles(fn (): string => $this->renderList($blocks, 0));
    }

    // Runs a render (also one that calls renderBlock() block by block, like entry content)
    // and puts the CSS of its styled blocks in front, once.
    public function withStyles(Closure $render): string
    {
        if ($this->rendering++ === 0) {
            $this->collected = [];
            $this->assetKeys = [];
        }

        try {
            $html = $render();
        } finally {
            $this->rendering--;
        }

        if ($this->rendering > 0) {
            return $html;
        }

        $assets = $this->assetTags();

        if ($this->collected === []) {
            return $assets['before'].$html.$assets['after'];
        }

        $css = implode('', $this->collected);
        $this->collected = [];

        return $assets['before'].'<style data-verso-block-styles>'.$css.'</style>'.$html.$assets['after'];
    }

    /**
     * Link and script tags of the assets the drawn blocks need, each once per render.
     *
     * @return array{before: string, after: string}
     */
    protected function assetTags(): array
    {
        $keys = array_keys($this->assetKeys);
        $this->assetKeys = [];

        if ($this->assets === null || $keys === []) {
            return ['before' => '', 'after' => ''];
        }

        // Each render prints its own tags, so cached HTML is complete. A second link to the same file
        // costs nothing, and a module script runs once per page however often it is named.
        return $this->assets->tags($keys);
    }

    /**
     * Link and script tags of some assets, for HTML drawn outside blocks
     * (like a theme builder header).
     *
     * @param  list<string>  $keys
     * @return array{before: string, after: string}
     */
    public function assetsFor(array $keys): array
    {
        foreach ($keys as $key) {
            $this->assetKeys[$key] = true;
        }

        return $this->assetTags();
    }

    // Keeps the assets a block type needs for the running render. Blocks drawn outside a render ask for them here too.
    public function needAssets(string $type): void
    {
        foreach ($this->assets?->forBlock($type) ?? [] as $key) {
            $this->assetKeys[$key] = true;
        }
    }

    // Unknown block types render nothing.
    /** @param array<mixed> $block */
    public function renderBlock(array $block, int $depth = 0): string
    {
        $data = BlockData::from($block, $depth);
        $type = $this->registry->find($data->type);

        if ($type === null || $depth >= $this->maxDepth || $this->skips($data)) {
            return '';
        }

        return $this->safely($data, fn (): string => $this->decorate($type->render($data, $this), $data));
    }

    // Runs the render of one block. When it fails, the error is logged and the page goes on without it:
    // visitors see nothing there, the visual editor sees a short note it can delete.
    public function safely(BlockData $block, Closure $render): string
    {
        try {
            return $render();
        } catch (Throwable $error) {
            report($error);

            return $this->markers ? $this->brokenNote($block) : '';
        }
    }

    // The note the visual editor shows in place of a block that could not be drawn.
    protected function brokenNote(BlockData $block): string
    {
        return '<div data-verso-block="'.e($block->id).'" data-verso-type="'.e($block->type).'" data-verso-broken role="note"'
            .' style="margin:1rem 0;padding:1rem;border:2px dashed #d97706;border-radius:8px;background:#fffbeb;color:#78350f;font:500 15px/1.4 system-ui,sans-serif">'
            .e(__('This part could not be shown. Delete it, or undo your last change. Visitors do not see it.')).'</div>';
    }

    // Turns edit mode on or off for the next renders of this request.
    public function showMarkers(bool $on = true): void
    {
        $this->markers = $on;
    }

    public function showsMarkers(): bool
    {
        return $this->markers;
    }

    // Adds the style class and, in edit mode, the editor markers to the first tag of a rendered block.
    public function decorate(string $html, BlockData $block): string
    {
        $attrs = $this->markers ? $this->withoutHidden($block->attrs) : $block->attrs;
        $html = $this->applyStyle($html, $attrs);

        if ($html !== '') {
            $this->needAssets($block->type);
        }

        return $this->markers ? $this->mark($html, $block) : $html;
    }

    // True when the block is hidden on every or some screen sizes (attrs.design.visibility.hidden).
    /** @param array<mixed> $attrs */
    public function isHidden(array $attrs): bool
    {
        $hidden = data_get($attrs, BlockStyleSchema::ATTR.'.'.BlockStyleSchema::HIDDEN_PATH);

        return $hidden === true || (is_array($hidden) && in_array(true, $hidden, true));
    }

    // A block hidden on every screen size is left out of the page, except in edit mode where it stays visible.
    public function skips(BlockData $block): bool
    {
        if ($this->markers) {
            return false;
        }

        $hidden = data_get($block->attrs, BlockStyleSchema::ATTR.'.'.BlockStyleSchema::HIDDEN_PATH);

        return $hidden === true || (is_array($hidden) && ($hidden['base'] ?? null) === true && ! in_array(false, $hidden, true));
    }

    // data-verso-block, data-verso-type and data-verso-hidden on the first tag, for the visual editor.
    protected function mark(string $html, BlockData $block): string
    {
        if ($block->id === '' || preg_match(self::FIRST_TAG, $html, $tag) !== 1) {
            return $html;
        }

        $markers = ' data-verso-block="'.e($block->id).'" data-verso-type="'.e($block->type).'"'
            .($this->isHidden($block->attrs) ? ' data-verso-hidden' : '');

        return $tag[1].$markers.($tag[2] ?? '').'>'.substr($html, strlen($tag[0]));
    }

    /**
     * The attrs without the hidden option, so edit mode still shows hidden blocks.
     *
     * @param  array<string, mixed>  $attrs
     * @return array<string, mixed>
     */
    protected function withoutHidden(array $attrs): array
    {
        if (! $this->isHidden($attrs)) {
            return $attrs;
        }

        Arr::forget($attrs, BlockStyleSchema::ATTR.'.'.BlockStyleSchema::HIDDEN_PATH);

        return $attrs;
    }

    // Adds the scoped style class of a block to its first HTML tag and keeps its CSS for the page.
    /** @param array<mixed> $attrs */
    protected function applyStyle(string $html, array $attrs): string
    {
        $style = $this->styles?->compile($attrs);

        if ($style === null || preg_match(self::FIRST_TAG, $html, $tag) !== 1) {
            return $html;
        }

        $this->collected[$style->class] = $style->css;
        $attributes = $tag[2] ?? '';

        $attributes = preg_match('/\sclass="([^"]*)"/', $attributes) === 1
            ? (string) preg_replace('/\sclass="([^"]*)"/', ' class="$1 '.$style->class.'"', $attributes, 1)
            : $attributes.' class="'.$style->class.'"';

        return $tag[1].$attributes.'>'.substr($html, strlen($tag[0]));
    }

    // Renders the children a block accepts. Other children are skipped.
    public function renderChildren(BlockData $parent): string
    {
        $type = $this->registry->find($parent->type);

        if ($type === null) {
            return '';
        }

        $children = array_filter(
            $parent->children,
            fn (array $child): bool => is_string($child['type'] ?? null)
                && ($this->dropRules?->accepts($type, $child['type']) ?? $type->acceptsChild($child['type'])),
        );

        return $this->renderList($children, $parent->depth + 1);
    }

    // Keeps only allowed inline tags and safe links. Everything else is removed.
    public function sanitizeInline(string $html): string
    {
        return $this->sanitizer->inline($html);
    }

    // Shortcodes (or other inline tokens) are expanded by this, when set.
    public function setInlineExpander(?InlineExpander $expander): void
    {
        $this->expander = $expander;
    }

    /**
     * Text parts and block parts of sanitized inline HTML. Without an expander it is one text part.
     *
     * @return list<array{html: string, block: bool}>
     */
    public function inlineSegments(string $html): array
    {
        return $this->expander?->segments($html) ?? [['html' => $html, 'block' => false]];
    }

    /**
     * @return list<string>
     */
    public function inlineNames(string $html): array
    {
        return $this->expander?->names($html) ?? [];
    }

    public function sanitizer(): HtmlSanitizer
    {
        return $this->sanitizer;
    }

    public function urls(): UrlSanitizer
    {
        return $this->urls;
    }

    /**
     * @param  array<int, mixed>  $blocks
     */
    protected function renderList(array $blocks, int $depth): string
    {
        $html = '';

        foreach ($blocks as $block) {
            if (is_array($block)) {
                $html .= $this->renderBlock($block, $depth);
            }
        }

        return $html;
    }
}
