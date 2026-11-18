<?php

namespace App\Verso\Easy\Sections;

use App\Verso\Editor\BlockRegistry;
use App\Verso\Plugins\PluginManager;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

// Makes core blocks with fresh, unique ids for ready sections. Use one kit per page, so ids never repeat on it.
class SectionKit
{
    // Design tokens of the site style. Themes set their values, so sections follow the chosen look.
    public const SPACE_SECTION = 'var:spacing.x-large';

    public const SPACE_LARGE = 'var:spacing.large';

    public const SPACE_MEDIUM = 'var:spacing.medium';

    public const SPACE_SMALL = 'var:spacing.small';

    public const RADIUS = 'var:radius.base';

    public const COLOR_ACCENT = 'var:color.accent';

    public const COLOR_SURFACE = 'var:color.surface';

    public const COLOR_BACKGROUND = 'var:color.background';

    // Length of the random part of a block id.
    protected const ID_LENGTH = 8;

    /** @var array<string, true> */
    protected array $used = [];

    public function __construct(
        protected ?PluginManager $plugins = null,
        protected ?BlockRegistry $blocks = null,
    ) {}

    // True when the plugin is turned on, so a section can use its blocks.
    public function hasPlugin(string $slug): bool
    {
        return $this->plugins?->isEnabled($slug) ?? false;
    }

    /**
     * True when the block type is known and these attrs pass its rules, like a form block that names a form that exists.
     *
     * @param  array<string, mixed>  $attrs
     */
    public function accepts(string $type, array $attrs): bool
    {
        $block = ($this->blocks ?? app(BlockRegistry::class))->find($type);

        return $block !== null && Validator::make($attrs, $block->rules())->passes();
    }

    // A new block id that this kit has not given before.
    public function id(): string
    {
        do {
            $id = 'b_'.Str::lower(Str::random(self::ID_LENGTH));
        } while (isset($this->used[$id]));

        $this->used[$id] = true;

        return $id;
    }

    /**
     * Any block.
     *
     * @param  array<string, mixed>  $attrs
     * @param  list<array<string, mixed>>  $children
     * @return array{id: string, type: string, attrs: array<string, mixed>, content: string, children: list<array<string, mixed>>}
     */
    public function block(string $type, array $attrs = [], string $content = '', array $children = []): array
    {
        return ['id' => $this->id(), 'type' => $type, 'attrs' => $attrs, 'content' => $content, 'children' => $children];
    }

    /**
     * A section box around other blocks, with room above and below.
     *
     * @param  list<array<string, mixed>|null>  $children  null children are left out
     * @param  array<string, mixed>  $design  extra style options (attrs.design)
     * @return array<string, mixed>
     */
    public function section(array $children, array $design = [], string $label = '', string $tag = 'section'): array
    {
        $style = array_replace_recursive([
            'spacing' => ['padding' => ['top' => self::SPACE_SECTION, 'bottom' => self::SPACE_SECTION]],
            'layout' => ['display' => 'flex', 'direction' => 'column', 'gap' => self::SPACE_MEDIUM],
        ], $design);

        return $this->block('section', array_filter(['tag' => $tag, 'label' => $label, 'design' => $style], fn (mixed $value): bool => $value !== ''), children: $this->present($children));
    }

    /**
     * A grid of cards: many columns on wide screens, two on tablets and one on phones.
     *
     * @param  list<array<string, mixed>|null>  $children
     * @return array<string, mixed>
     */
    public function grid(array $children, int $columns = 3): array
    {
        $columns = max(1, min(4, $columns));

        return $this->block('section', ['tag' => 'div', 'design' => [
            'layout' => [
                'display' => 'grid',
                'columns' => ['base' => $columns, 'tablet' => min(2, $columns), 'mobile' => 1],
                'gap' => self::SPACE_LARGE,
            ],
        ]], children: $this->present($children));
    }

    /**
     * A soft panel with rounded corners, for one card of a grid.
     *
     * @param  list<array<string, mixed>|null>  $children
     * @return array<string, mixed>
     */
    public function card(array $children, bool $highlight = false): array
    {
        return $this->block('section', ['tag' => 'div', 'design' => [
            'spacing' => ['padding' => [
                'top' => self::SPACE_LARGE, 'right' => self::SPACE_LARGE, 'bottom' => self::SPACE_LARGE, 'left' => self::SPACE_LARGE,
            ]],
            'layout' => ['display' => 'flex', 'direction' => 'column', 'gap' => self::SPACE_SMALL],
            'color' => ['background' => self::COLOR_SURFACE],
            'border' => array_filter([
                'radius' => self::RADIUS,
                'width' => $highlight ? '2px' : null,
                'style' => $highlight ? 'solid' : null,
                'color' => $highlight ? self::COLOR_ACCENT : null,
            ]),
        ]], children: $this->present($children));
    }

    /**
     * @param  array<string, mixed>  $design
     * @return array<string, mixed>
     */
    public function heading(string $text, int $level = 2, array $design = []): array
    {
        return $this->block('heading', array_filter(['level' => max(1, min(6, $level)), 'design' => $design]), e($text));
    }

    // A paragraph of plain text. The text is escaped and line breaks are kept.
    /**
     * @param  array<string, mixed>  $design
     * @return array<string, mixed>
     */
    public function paragraph(string $text, array $design = []): array
    {
        return $this->block('paragraph', array_filter(['design' => $design]), '<p>'.nl2br(e(trim($text)), false).'</p>');
    }

    /**
     * One paragraph per block of text, split on empty lines.
     *
     * @return list<array<string, mixed>>
     */
    public function paragraphs(string $text): array
    {
        $parts = array_filter(array_map(trim(...), preg_split('/\R\s*\R/', $text) ?: []), fn (string $part): bool => $part !== '');

        return array_values(array_map(fn (string $part): array => $this->paragraph($part), $parts));
    }

    /**
     * A bulleted list of plain text items.
     *
     * @param  list<string>  $items
     * @return array<string, mixed>|null
     */
    public function list(array $items, bool $ordered = false): ?array
    {
        $html = implode('', array_map(fn (string $item): string => '<li>'.e($item).'</li>', array_values(array_filter($items, fn (string $item): bool => trim($item) !== ''))));

        return $html === '' ? null : $this->block('list', ['ordered' => $ordered], $html);
    }

    /**
     * A link styled as a button, or null without a label or address.
     *
     * @param  array{label: string, url: string}|null  $button
     * @return array<string, mixed>|null
     */
    public function button(?array $button, string $style = 'primary'): ?array
    {
        if ($button === null || trim($button['label']) === '' || trim($button['url']) === '') {
            return null;
        }

        return $this->block('button', ['label' => $button['label'], 'url' => $button['url'], 'style' => $style]);
    }

    /**
     * An image with rounded corners, or null when the picture has no source.
     *
     * @param  array<string, mixed>|null  $image  attrs like mediaId, src, alt, width, height
     * @return array<string, mixed>|null
     */
    public function image(?array $image, string $caption = ''): ?array
    {
        $attrs = $this->imageAttrs($image);

        if ($attrs === null) {
            return null;
        }

        return $this->block('image', array_filter([...$attrs, 'caption' => $caption, 'design' => ['border' => ['radius' => self::RADIUS]]], fn (mixed $value): bool => $value !== ''));
    }

    /**
     * A gallery of pictures, or null without any picture.
     *
     * @param  list<array<string, mixed>>  $images
     * @return array<string, mixed>|null
     */
    public function gallery(array $images, int $columns = 3): ?array
    {
        $items = array_values(array_filter(array_map($this->imageAttrs(...), $images)));

        if ($items === []) {
            return null;
        }

        $ids = array_values(array_filter(array_map(fn (array $item): ?int => $item['mediaId'] ?? null, $items)));

        return $this->block('gallery', array_filter([
            'mediaIds' => $ids,
            'columns' => max(1, min(6, $columns)),
            'images' => $items,
        ], fn (mixed $value): bool => $value !== []));
    }

    /**
     * Side by side columns that stack on small screens.
     *
     * @param  list<list<array<string, mixed>|null>>  $columns  the blocks of each column
     * @return array<string, mixed>
     */
    public function columns(array $columns): array
    {
        return $this->block('columns', children: array_map(
            fn (array $blocks): array => $this->block('column', children: $this->present($blocks)),
            $columns,
        ));
    }

    /**
     * Clean image attrs. A source is needed, because gallery images and themes without media need one.
     *
     * @param  array<string, mixed>|null  $image
     * @return array{mediaId?: int, src?: string, alt?: string, width?: int, height?: int}|null
     */
    protected function imageAttrs(?array $image): ?array
    {
        if ($image === null) {
            return null;
        }

        $attrs = array_filter([
            'mediaId' => is_int($image['mediaId'] ?? null) && $image['mediaId'] > 0 ? $image['mediaId'] : null,
            'src' => is_string($image['src'] ?? null) && $image['src'] !== '' ? $image['src'] : null,
            'alt' => is_string($image['alt'] ?? null) ? $image['alt'] : null,
            'width' => is_int($image['width'] ?? null) && $image['width'] > 0 ? $image['width'] : null,
            'height' => is_int($image['height'] ?? null) && $image['height'] > 0 ? $image['height'] : null,
        ], fn (mixed $value): bool => $value !== null);

        return isset($attrs['src']) ? $attrs : null;
    }

    /**
     * The children without the null ones.
     *
     * @param  list<array<string, mixed>|null>  $blocks
     * @return list<array<string, mixed>>
     */
    protected function present(array $blocks): array
    {
        return array_values(array_filter($blocks, fn (?array $block): bool => $block !== null));
    }
}
