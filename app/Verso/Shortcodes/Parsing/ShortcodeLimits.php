<?php

namespace App\Verso\Shortcodes\Parsing;

// Limits that keep parsing cheap and safe.
final readonly class ShortcodeLimits
{
    public function __construct(
        public int $maxDepth = 3,
        public int $maxPerText = 50,
        public int $maxAttributeLength = 500,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            maxDepth: max(1, (int) config('verso.shortcodes.max_depth')),
            maxPerText: max(1, (int) config('verso.shortcodes.max_per_text')),
            maxAttributeLength: max(1, (int) config('verso.shortcodes.max_attribute_length')),
        );
    }
}
