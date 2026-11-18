<?php

namespace App\Verso\Content;

// Describes a kind of entry, like "post" or "page".
final readonly class ContentType
{
    /**
     * @param  list<string>  $taxonomies
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $pluralLabel,
        public bool $hierarchical = false,
        public bool $hasArchive = false,
        public string $routeBase = '',
        public string $icon = 'entries',
        public int $menuOrder = 10,
        public array $taxonomies = [],
    ) {}

    // The URL path for an entry slug path, like "/blog/my-post" or "/about/team".
    public function pathFor(string $slugPath): string
    {
        return '/'.ltrim(trim($this->routeBase, '/').'/'.$slugPath, '/');
    }

    /**
     * @return array{name: string, label: string, pluralLabel: string, hierarchical: bool, hasArchive: bool, taxonomies: list<string>, icon: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'pluralLabel' => $this->pluralLabel,
            'hierarchical' => $this->hierarchical,
            'hasArchive' => $this->hasArchive,
            'taxonomies' => $this->taxonomies,
            'icon' => $this->icon,
        ];
    }
}
