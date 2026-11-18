<?php

namespace App\Verso\Importer\Content;

// Turns classic editor content (no block comments) into Verso blocks. Blank lines split blocks.
class ClassicConverter
{
    public function __construct(protected ShortcodeMapper $shortcodes) {}

    // Tags that make a chunk too complex for a paragraph, so it is kept as HTML.
    protected const BLOCK_TAGS = 'div|table|figure|iframe|script|form|section|article|aside|video|audio|object|dl|h[1-6]|ul|ol|blockquote|pre|hr|p';

    /**
     * @return list<array<string, mixed>>
     */
    public function convert(string $content, BlockBuilder $builder): array
    {
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $content = (string) preg_replace('/<!--(more|nextpage|noteaser)(.*?)-->/i', '', $content);
        $blocks = [];

        foreach (preg_split('/\n\s*\n/', $content) ?: [] as $chunk) {
            $chunk = trim($chunk);

            if ($chunk !== '') {
                $blocks[] = $this->chunk($chunk, $builder);
            }
        }

        return $blocks;
    }

    // One chunk of text between blank lines.
    /** @return array<string, mixed> */
    protected function chunk(string $chunk, BlockBuilder $builder): array
    {
        return $this->heading($chunk, $builder)
            ?? $this->separator($chunk, $builder)
            ?? $this->list($chunk, $builder)
            ?? $this->quote($chunk, $builder)
            ?? $this->code($chunk, $builder)
            ?? $this->caption($chunk, $builder)
            ?? $this->gallery($chunk, $builder)
            ?? $this->embed($chunk, $builder)
            ?? $this->shortcodes->map($chunk, $builder)
            ?? $this->image($chunk, $builder)
            ?? $this->paragraph($chunk, $builder);
    }

    /** @return array<string, mixed>|null */
    protected function heading(string $chunk, BlockBuilder $builder): ?array
    {
        if (preg_match('#^<h([1-6])[^>]*>(.*)</h\1>$#is', $chunk, $match) !== 1) {
            return null;
        }

        return $builder->heading((int) $match[1], $match[2]);
    }

    /** @return array<string, mixed>|null */
    protected function separator(string $chunk, BlockBuilder $builder): ?array
    {
        return preg_match('#^<hr[^>]*/?>$#i', $chunk) === 1 ? $builder->separator() : null;
    }

    /** @return array<string, mixed>|null */
    protected function list(string $chunk, BlockBuilder $builder): ?array
    {
        if (preg_match('#^<(ul|ol)[^>]*>(.*)</\1>$#is', $chunk, $match) !== 1) {
            return null;
        }

        return $builder->list(strtolower($match[1]) === 'ol', $match[2]);
    }

    /** @return array<string, mixed>|null */
    protected function quote(string $chunk, BlockBuilder $builder): ?array
    {
        if (preg_match('#^<blockquote[^>]*>(.*)</blockquote>$#is', $chunk, $match) !== 1) {
            return null;
        }

        $fragment = HtmlFragment::parse($match[1]);
        $citation = trim((string) $fragment->first('cite')?->textContent);
        $fragment->remove('cite');
        $paragraphs = array_map(fn ($p): string => $fragment->inner($p), $fragment->all('p'));
        $html = $paragraphs === [] ? $fragment->html() : implode('<br>', $paragraphs);

        return $builder->quote($this->lines($html), $citation);
    }

    /** @return array<string, mixed>|null */
    protected function code(string $chunk, BlockBuilder $builder): ?array
    {
        if (preg_match('#^<pre[^>]*>(.*)</pre>$#is', $chunk, $match) !== 1) {
            return null;
        }

        $code = (string) preg_replace('#^<code[^>]*>(.*)</code>$#is', '$1', $match[1]);

        return $builder->code(html_entity_decode($code, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    // The [caption] shortcode wraps an image and its caption text.
    /** @return array<string, mixed>|null */
    protected function caption(string $chunk, BlockBuilder $builder): ?array
    {
        if (preg_match('#^\[caption([^\]]*)\](.*)\[/caption\]$#is', $chunk, $match) !== 1) {
            return null;
        }

        $fragment = HtmlFragment::parse($match[2]);
        $img = $fragment->first('img');

        if ($img === null) {
            return null;
        }

        $caption = trim((string) preg_replace('#^.*<img[^>]*>(\s*</a>)?#is', '', $match[2]));
        $width = preg_match('/width="(\d+)"/', $match[1], $size) === 1 ? (int) $size[1] : null;

        return $builder->image($img, null, $caption, $width);
    }

    // The [gallery ids="1,2,3"] shortcode.
    /** @return array<string, mixed>|null */
    protected function gallery(string $chunk, BlockBuilder $builder): ?array
    {
        if (preg_match('#^\[gallery[^\]]*\bids="([\d,\s]+)"[^\]]*\]$#i', $chunk, $match) !== 1) {
            return null;
        }

        $ids = array_values(array_filter(array_map('intval', explode(',', $match[1]))));

        return $builder->gallery($ids);
    }

    // An [embed] shortcode or a URL alone on its line (WordPress turns those into embeds).
    /** @return array<string, mixed>|null */
    protected function embed(string $chunk, BlockBuilder $builder): ?array
    {
        if (preg_match('#^\[embed[^\]]*\](https?://\S+)\[/embed\]$#i', $chunk, $match) === 1
            || preg_match('#^(https?://[^\s<>"]+)$#i', $chunk, $match) === 1) {
            return $builder->embed($match[1]);
        }

        return null;
    }

    // An image alone, maybe inside a link.
    /** @return array<string, mixed>|null */
    protected function image(string $chunk, BlockBuilder $builder): ?array
    {
        if (preg_match('#^(<a[^>]*>)?\s*<img[^>]*>\s*(</a>)?$#is', $chunk) !== 1) {
            return null;
        }

        $img = HtmlFragment::parse($chunk)->first('img');

        return $img === null ? null : $builder->image($img);
    }

    /** @return array<string, mixed> */
    protected function paragraph(string $chunk, BlockBuilder $builder): array
    {
        if (preg_match('#^<p[^>]*>(.*)</p>$#is', $chunk, $match) === 1 && stripos($match[1], '<p') === false) {
            return $builder->paragraph($this->lines($match[1]));
        }

        if (preg_match('#<('.self::BLOCK_TAGS.')[\s>/]#i', $chunk) === 1) {
            return $builder->html($chunk);
        }

        return $builder->paragraph($this->lines($chunk));
    }

    // Single line breaks become <br>, like WordPress does.
    protected function lines(string $html): string
    {
        return (string) preg_replace('/\s*\n\s*/', '<br>', trim($html));
    }
}
