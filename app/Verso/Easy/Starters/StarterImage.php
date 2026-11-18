<?php

namespace App\Verso\Easy\Starters;

// A sample picture painted for a starter site. Sections name it in their content.
final readonly class StarterImage
{
    /**
     * @param  string  $style  a DemoImagePainter style, like "still-life" or "portrait"
     * @param  array{sky: string, horizon: string, sun: string, hills: list<string>}  $palette  hex colors
     */
    public function __construct(
        public string $name,
        public string $alt,
        public string $style,
        public array $palette,
    ) {}

    // Portraits are square, other pictures use the wide size.
    public function isPortrait(): bool
    {
        return $this->style === 'portrait';
    }
}
