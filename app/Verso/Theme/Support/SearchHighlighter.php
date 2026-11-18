<?php

namespace App\Verso\Theme\Support;

use Illuminate\Support\HtmlString;

// Escapes a text and wraps the words of a search in <mark> tags, for search result pages.
class SearchHighlighter
{
    // Words shorter than this are not marked, so "a" does not light up every line.
    public function __construct(protected int $minWordLength = 2) {}

    public function mark(string $text, string $search): HtmlString
    {
        $words = array_filter(
            preg_split('/\s+/u', trim($search)) ?: [],
            fn (string $word): bool => mb_strlen($word) >= $this->minWordLength,
        );

        if ($words === []) {
            return new HtmlString(e($text));
        }

        // Split the plain text first and escape each piece, so a word never matches inside an HTML entity.
        $pattern = '/('.implode('|', array_map(fn (string $word): string => preg_quote($word, '/'), $words)).')/iu';
        $parts = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
        $html = '';

        foreach ($parts as $index => $part) {
            $html .= $index % 2 === 1 ? '<mark>'.e($part).'</mark>' : e($part);
        }

        return new HtmlString($html);
    }
}
