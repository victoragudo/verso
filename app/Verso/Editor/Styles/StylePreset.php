<?php

namespace App\Verso\Editor\Styles;

// A named set of block styles, like "Card" or "Hero". Blocks point to it with attrs.preset.
final readonly class StylePreset
{
    // Where a preset comes from. Saved presets can replace core and theme ones with the same key.
    public const string CORE = 'core';

    public const string THEME = 'theme';

    public const string SAVED = 'saved';

    // Preset keys: lowercase letters, digits and dashes.
    public const string KEY_PATTERN = '/^[a-z0-9][a-z0-9-]{0,39}$/';

    /**
     * @param  array<string, mixed>  $design  the same shape as attrs.design
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $design,
        public string $source = self::CORE,
        // True when a saved copy replaces a core or theme preset with the same key.
        public bool $changed = false,
    ) {}

    /** @return array{key: string, label: string, design: array<string, mixed>, source: string, changed: bool} */
    public function toArray(): array
    {
        return ['key' => $this->key, 'label' => $this->label, 'design' => $this->design, 'source' => $this->source, 'changed' => $this->changed];
    }
}
