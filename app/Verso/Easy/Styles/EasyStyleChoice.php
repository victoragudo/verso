<?php

namespace App\Verso\Easy\Styles;

// What a person picked on "Make it yours": palette colors by role, a style preset, a font pair and the logo.
final readonly class EasyStyleChoice
{
    /**
     * @param  array<string, string>  $colors  role (accent, background, text, muted, surface) => hex color
     * @param  bool  $setLogo  false keeps the logo as it is, true saves $logo (null removes it)
     */
    public function __construct(
        public array $colors = [],
        public ?string $preset = null,
        public ?string $fontPair = null,
        public ?int $logo = null,
        public bool $setLogo = false,
    ) {}
}
