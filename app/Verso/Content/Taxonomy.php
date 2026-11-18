<?php

namespace App\Verso\Content;

// Describes a way to group entries, like "category" or "tag".
final readonly class Taxonomy
{
    /**
     * @param  list<string>  $contentTypes
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $pluralLabel,
        public bool $hierarchical = false,
        public array $contentTypes = [],
        public string $routeBase = '',
        public string $icon = 'tags',
        public int $menuOrder = 50,
    ) {}

    public function appliesTo(string $contentType): bool
    {
        return in_array($contentType, $this->contentTypes, true);
    }

    // The public URL path for a term slug, like "/category/travel".
    public function pathFor(string $slug): string
    {
        return '/'.ltrim(trim($this->routeBase, '/').'/'.$slug, '/');
    }

    /**
     * @return array{name: string, label: string, pluralLabel: string, hierarchical: bool, icon: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'pluralLabel' => $this->pluralLabel,
            'hierarchical' => $this->hierarchical,
            'icon' => $this->icon,
        ];
    }
}
