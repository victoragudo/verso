<?php

namespace App\Verso\Importer\Content;

// Turns parsed Gutenberg blocks into Verso blocks. Unknown blocks are kept as HTML.
class GutenbergConverter
{
    // Blocks that only group other blocks. Their children are kept, the wrapper is dropped.
    protected const WRAPPERS = ['core/group', 'core/row', 'core/stack'];

    // Blocks with nothing worth keeping.
    protected const DROPPED = ['core/more', 'core/nextpage', 'core/spacer'];

    public function __construct(protected ClassicConverter $classic) {}

    /**
     * @param  list<ParsedBlock>  $blocks
     * @return list<array<string, mixed>>
     */
    public function convert(array $blocks, BlockBuilder $builder): array
    {
        $converted = [];

        foreach ($blocks as $block) {
            array_push($converted, ...$this->block($block, $builder));
        }

        return $converted;
    }

    /**
     * One Gutenberg block can give zero, one or many Verso blocks.
     *
     * @return list<array<string, mixed>>
     */
    protected function block(ParsedBlock $block, BlockBuilder $builder): array
    {
        $name = $block->name;

        if ($name === null || $name === 'core/freeform') {
            return $this->classic->convert($block->html(), $builder);
        }

        if (in_array($name, self::DROPPED, true)) {
            return [];
        }

        if (in_array($name, self::WRAPPERS, true)) {
            return $this->convert($block->innerBlocks, $builder);
        }

        if ($name === 'core/embed' || str_starts_with($name, 'core-embed/')) {
            return [$this->embed($block, $builder)];
        }

        $converted = match ($name) {
            'core/paragraph' => $this->paragraph($block, $builder),
            'core/heading' => $this->heading($block, $builder),
            'core/list' => $this->list($block, $builder),
            'core/quote', 'core/pullquote' => $this->quote($block, $builder),
            'core/image' => $this->image($block, $builder),
            'core/gallery' => $this->gallery($block, $builder),
            'core/code', 'core/preformatted' => $this->code($block, $builder),
            'core/separator' => [$builder->separator()],
            'core/columns' => $this->columns($block, $builder),
            'core/buttons' => $this->buttons($block, $builder),
            'core/button' => $this->button($block, $builder),
            'core/html' => $this->html($block, $builder),
            'core/shortcode' => $this->classic->convert($block->html(), $builder),
            default => null,
        };

        return $converted ?? $this->html($block, $builder);
    }

    /** @return list<array<string, mixed>> */
    protected function paragraph(ParsedBlock $block, BlockBuilder $builder): array
    {
        $html = HtmlFragment::parse($block->html())->innerHtml('p');

        return $html === '' ? [] : [$builder->paragraph($html)];
    }

    /** @return list<array<string, mixed>> */
    protected function heading(ParsedBlock $block, BlockBuilder $builder): array
    {
        $fragment = HtmlFragment::parse($block->html());
        $tag = $fragment->first('h1', 'h2', 'h3', 'h4', 'h5', 'h6');
        $level = is_numeric($block->attr('level')) ? (int) $block->attr('level') : (int) substr($tag->tagName ?? 'h2', 1);

        return $tag === null ? [] : [$builder->heading($level, $fragment->inner($tag))];
    }

    /** @return list<array<string, mixed>> */
    protected function list(ParsedBlock $block, BlockBuilder $builder): array
    {
        $fragment = HtmlFragment::parse($block->html());
        $list = $fragment->first('ul', 'ol');

        if ($list === null) {
            return [];
        }

        $ordered = $block->attr('ordered') === true || strtolower($list->tagName) === 'ol';

        return [$builder->list($ordered, $fragment->inner($list))];
    }

    /** @return list<array<string, mixed>> */
    protected function quote(ParsedBlock $block, BlockBuilder $builder): array
    {
        $fragment = HtmlFragment::parse($block->html());
        $citation = trim((string) $fragment->first('cite')?->textContent);
        $fragment->remove('cite');
        $paragraphs = array_map(fn ($p): string => $fragment->inner($p), $fragment->all('p'));
        $html = $paragraphs === [] ? $fragment->innerHtml('blockquote') : implode('<br>', $paragraphs);

        return [$builder->quote($html, $citation)];
    }

    /** @return list<array<string, mixed>> */
    protected function image(ParsedBlock $block, BlockBuilder $builder): array
    {
        $fragment = HtmlFragment::parse($block->html());
        $img = $fragment->first('img');

        if ($img === null) {
            return [];
        }

        return [$builder->image(
            $img,
            $this->int($block->attr('id')),
            $fragment->innerHtml('figcaption'),
            $this->int($block->attr('width')),
            $this->int($block->attr('height')),
        )];
    }

    /** @return list<array<string, mixed>> */
    protected function gallery(ParsedBlock $block, BlockBuilder $builder): array
    {
        $ids = array_values(array_filter(array_map($this->int(...), (array) ($block->attr('ids') ?? []))));

        return [$builder->gallery($ids, HtmlFragment::parse($block->html())->all('img'))];
    }

    /** @return array<string, mixed> */
    protected function embed(ParsedBlock $block, BlockBuilder $builder): array
    {
        $fragment = HtmlFragment::parse($block->html());
        $caption = $fragment->innerHtml('figcaption');
        $fragment->remove('figcaption');
        $url = is_string($block->attr('url')) ? $block->attr('url') : $fragment->text();
        $provider = is_string($block->attr('providerNameSlug')) ? $block->attr('providerNameSlug') : '';

        return $builder->embed($url, $provider, $caption);
    }

    /** @return list<array<string, mixed>> */
    protected function code(ParsedBlock $block, BlockBuilder $builder): array
    {
        $fragment = HtmlFragment::parse($block->html());
        $code = $fragment->first('code') ?? $fragment->first('pre');
        $class = (string) $code?->getAttribute('class');
        $language = is_string($block->attr('language')) ? $block->attr('language') : null;

        if ($language === null && preg_match('/\blanguage-([a-z0-9+#-]+)/i', $class, $match) === 1) {
            $language = strtolower($match[1]);
        }

        return [$builder->code((string) $code?->textContent, $language)];
    }

    /** @return list<array<string, mixed>> */
    protected function columns(ParsedBlock $block, BlockBuilder $builder): array
    {
        $columns = [];

        foreach ($block->innerBlocks as $column) {
            if ($column->name !== 'core/column') {
                continue;
            }

            $width = $column->attr('width');
            $columns[] = $builder->make(
                'column',
                is_string($width) ? ['width' => $width] : [],
                '',
                $this->convert($column->innerBlocks, $builder),
            );
        }

        return [$builder->make('columns', [], '', $columns)];
    }

    /** @return list<array<string, mixed>> */
    protected function buttons(ParsedBlock $block, BlockBuilder $builder): array
    {
        $buttons = [];

        foreach ($block->innerBlocks as $inner) {
            array_push($buttons, ...$this->button($inner, $builder));
        }

        return $buttons;
    }

    /** @return list<array<string, mixed>> */
    protected function button(ParsedBlock $block, BlockBuilder $builder): array
    {
        $link = HtmlFragment::parse($block->html())->first('a');

        if ($link === null) {
            return [];
        }

        $class = (string) ($block->attr('className') ?? '');
        $style = str_contains($class, 'is-style-outline') ? 'outline' : 'fill';

        return [$builder->button($link->getAttribute('href'), $link->textContent, $style)];
    }

    /** @return list<array<string, mixed>> */
    protected function html(ParsedBlock $block, BlockBuilder $builder): array
    {
        $html = trim($block->html());

        return $html === '' ? [] : [$builder->html($html)];
    }

    protected function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
