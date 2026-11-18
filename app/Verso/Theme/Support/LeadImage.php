<?php

namespace App\Verso\Theme\Support;

// Makes the first image of a page load right away with a high priority, because it is likely the largest paint.
class LeadImage
{
    // Changes the first <img> tag of the HTML from lazy to eager loading with fetchpriority="high".
    public function promote(string $html): string
    {
        if (preg_match('/<img\b[^>]*>/i', $html, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return $html;
        }

        [$tag, $offset] = $match[0];
        $promoted = preg_replace('/\sloading="lazy"/i', ' loading="eager"', $tag) ?? $tag;

        if (! str_contains(strtolower($promoted), 'fetchpriority=')) {
            $promoted = substr($promoted, 0, -1).' fetchpriority="high">';
        }

        return substr_replace($html, $promoted, $offset, strlen($tag));
    }
}
