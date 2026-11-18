<?php

namespace App\Verso\Plugins\Admin;

use App\Verso\Access\Capabilities\CapabilityDefinition;
use App\Verso\Core\Admin\NavGroup;

// Where a plugin puts its admin pages: one group and a list of links, from the "admin" part of verso.json.
final readonly class PluginAdminMenu
{
    // Keys of items and icons: lowercase words joined by dashes, or camelCase icon names.
    public const KEY_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public const ICON_PATTERN = '/^[A-Za-z][A-Za-z0-9-]{0,39}$/';

    // Plugins may only link to admin pages.
    public const ROUTE_PATTERN = '/^admin\.[a-z0-9][a-z0-9._-]*$/';

    /**
     * @param  list<PluginAdminItem>  $items
     * @param  list<string>  $keywords
     */
    public function __construct(
        public NavGroup $group,
        public ?string $label,
        public ?string $icon,
        public array $items,
        public array $keywords = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data  checked manifest data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            group: NavGroup::from((string) $data['group']),
            label: isset($data['label']) ? (string) $data['label'] : null,
            icon: isset($data['icon']) ? (string) $data['icon'] : null,
            items: array_values(array_map(
                fn (array $item): PluginAdminItem => PluginAdminItem::fromArray($item),
                array_filter((array) ($data['items'] ?? []), 'is_array'),
            )),
            keywords: self::cleanKeywords($data['keywords'] ?? []),
        );
    }

    /**
     * Validation rules for the "admin" object, used by PluginManifest.
     *
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        $maxItems = max(1, (int) config('verso.admin.nav.max_plugin_items'));
        $maxKeywords = max(0, (int) config('verso.admin.nav.max_keywords'));

        return [
            'admin' => ['nullable', 'array'],
            'admin.group' => ['required_with:admin', 'string', 'in:'.implode(',', array_column(NavGroup::cases(), 'value'))],
            'admin.label' => ['nullable', 'string', 'max:60'],
            'admin.icon' => ['nullable', 'string', 'regex:'.self::ICON_PATTERN],
            'admin.keywords' => ['nullable', 'array', 'list', 'max:'.$maxKeywords],
            'admin.keywords.*' => ['string', 'max:60'],
            'admin.items' => ['required_with:admin', 'array', 'list', 'min:1', 'max:'.$maxItems],
            'admin.items.*' => ['array'],
            'admin.items.*.key' => ['required', 'string', 'max:40', 'regex:'.self::KEY_PATTERN, 'distinct'],
            'admin.items.*.label' => ['required', 'string', 'max:60'],
            'admin.items.*.route' => ['required', 'string', 'max:120', 'regex:'.self::ROUTE_PATTERN],
            'admin.items.*.icon' => ['nullable', 'string', 'regex:'.self::ICON_PATTERN],
            'admin.items.*.capability' => ['nullable', 'string', 'max:120', 'regex:'.CapabilityDefinition::KEY_PATTERN],
            'admin.items.*.keywords' => ['nullable', 'array', 'list', 'max:'.$maxKeywords],
            'admin.items.*.keywords.*' => ['string', 'max:60'],
        ];
    }

    /**
     * Trimmed, lowercase and unique search words.
     *
     * @return list<string>
     */
    public static function cleanKeywords(mixed $keywords): array
    {
        $words = array_map(
            fn (mixed $word): string => mb_strtolower(trim((string) $word)),
            array_filter((array) $keywords, 'is_string'),
        );

        return array_values(array_unique(array_filter($words, fn (string $word): bool => $word !== '')));
    }

    // Plugins with several links, and every plugin in Tools, get one parent item with the links inside.
    public function hasParent(): bool
    {
        return $this->group !== NavGroup::Dashboard && ($this->group === NavGroup::Tools || count($this->items) > 1);
    }

    /**
     * Data for the admin UI and the plugin JSON.
     *
     * @return array{group: string, label: string|null, icon: string|null, items: list<array{key: string, label: string, route: string}>}
     */
    public function toArray(): array
    {
        return [
            'group' => $this->group->value,
            'label' => $this->label,
            'icon' => $this->icon,
            'items' => array_map(fn (PluginAdminItem $item): array => [
                'key' => $item->key,
                'label' => $item->label,
                'route' => $item->route,
            ], $this->items),
        ];
    }
}
