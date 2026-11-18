<?php

namespace App\Verso\Easy\Styles\Core;

use App\Verso\Easy\Styles\StylePreset;
use App\Verso\Easy\Styles\StylePresetRegistry;

// Registers the four core style presets (calm, bold, playful, elegant) through the public registry API.
class CoreStylePresets
{
    public function register(StylePresetRegistry $registry): void
    {
        $registry->register(new StylePreset(
            key: 'calm',
            label: 'Calm',
            description: 'Soft corners and room to breathe. Easy on the eyes.',
            tokens: $this->tokens('6px', ['0.5rem', '1rem', '1.5rem', '2.5rem']),
            fontPair: 'classic',
            order: 10,
        ));
        $registry->register(new StylePreset(
            key: 'bold',
            label: 'Striking',
            description: 'Square corners, strong headings and big gaps. Hard to miss.',
            tokens: $this->tokens('0px', ['0.5rem', '1rem', '2rem', '3.5rem']),
            fontPair: 'modern',
            order: 20,
        ));
        $registry->register(new StylePreset(
            key: 'playful',
            label: 'Playful',
            description: 'Round corners and friendly letters. Warm and relaxed.',
            tokens: $this->tokens('16px', ['0.75rem', '1.25rem', '2rem', '3rem']),
            fontPair: 'friendly',
            order: 30,
        ));
        $registry->register(new StylePreset(
            key: 'elegant',
            label: 'Elegant',
            description: 'Fine corners, classic serif letters and lots of white space.',
            tokens: $this->tokens('2px', ['0.5rem', '1.25rem', '2rem', '4rem']),
            fontPair: 'editorial',
            order: 40,
        ));
    }

    /**
     * Corner and spacing tokens. Themes only get the ones they declare.
     *
     * @param  array{string, string, string, string}  $spacing  small, medium, large, extra large
     * @return array<string, string>
     */
    protected function tokens(string $radius, array $spacing): array
    {
        return [
            'radius.base' => $radius,
            'spacing.small' => $spacing[0],
            'spacing.medium' => $spacing[1],
            'spacing.large' => $spacing[2],
            'spacing.x-large' => $spacing[3],
        ];
    }
}
