<?php

namespace App\Verso\Plugins\Admin;

use App\Verso\Core\Enums\Capability;

// One sidebar link a plugin declares in the "admin" part of verso.json.
final readonly class PluginAdminItem
{
    /**
     * @param  list<string>  $keywords
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $route,
        public ?string $icon = null,
        public Capability|string|null $capability = null,
        public array $keywords = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data  checked manifest data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            key: (string) $data['key'],
            label: (string) $data['label'],
            route: (string) $data['route'],
            icon: isset($data['icon']) ? (string) $data['icon'] : null,
            capability: isset($data['capability']) ? (Capability::tryFrom((string) $data['capability']) ?? (string) $data['capability']) : null,
            keywords: PluginAdminMenu::cleanKeywords($data['keywords'] ?? []),
        );
    }
}
