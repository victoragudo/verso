<?php

namespace App\Verso\System\Config;

use App\Verso\I18n\Translate;

// One page of the System settings area, like "Uploads and media" or a plugin.
final readonly class ConfigGroup
{
    /**
     * @param  array<string, string>  $descriptionReplace
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $description = '',
        public string $icon = 'settings',
        public int $order = 100,
        public ?string $plugin = null,
        public array $descriptionReplace = [],
    ) {}

    /**
     * @return array{key: string, label: string, description: string, icon: string, plugin: string|null}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => Translate::text($this->label, plugin: $this->plugin),
            'description' => $this->description === '' ? '' : Translate::text($this->description, $this->translatedDescriptionReplace(), $this->plugin),
            'icon' => $this->icon,
            'plugin' => $this->plugin,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function translatedDescriptionReplace(): array
    {
        return array_map(fn (string $value): string => Translate::text($value, plugin: $this->plugin), $this->descriptionReplace);
    }
}
