<?php

namespace App\Verso\Theme\Support;

use Illuminate\Support\Str;

// Builds a table of contents from the h2 headings of rendered entry HTML and gives each heading an id to link to.
final readonly class TableOfContents
{
    /**
     * @param  list<array{id: string, text: string}>  $items
     */
    public function __construct(
        public string $html,
        public array $items,
    ) {}

    public static function from(string $html): self
    {
        $items = [];
        $used = [];

        $html = (string) preg_replace_callback('/<h2(\s[^>]*)?>(.*?)<\/h2>/is', function (array $match) use (&$items, &$used): string {
            $attributes = $match[1];
            $text = trim(html_entity_decode(strip_tags($match[2]), ENT_QUOTES | ENT_HTML5));

            if (preg_match('/\sid="([^"]+)"/', $attributes, $existing) === 1) {
                $id = $existing[1];
            } else {
                $id = self::uniqueId(Str::slug($text) ?: 'section', $used);
                $attributes .= ' id="'.e($id).'"';
            }

            $used[$id] = true;

            if ($text !== '') {
                $items[] = ['id' => $id, 'text' => $text];
            }

            return '<h2'.$attributes.'>'.$match[2].'</h2>';
        }, $html);

        return new self($html, $items);
    }

    // True when the entry has enough sections to need a table of contents.
    public function isUseful(int $minimum): bool
    {
        return count($this->items) >= max(1, $minimum);
    }

    /** @param array<string, true> $used */
    private static function uniqueId(string $base, array $used): string
    {
        $id = $base;

        for ($number = 2; isset($used[$id]); $number++) {
            $id = $base.'-'.$number;
        }

        return $id;
    }
}
