<?php

namespace App\Verso\Content;

use App\Verso\Content\Models\Entry;
use App\Verso\Core\Admin\AdminNav;
use App\Verso\Core\Admin\NavItem;
use App\Verso\Core\Enums\Capability;

// Adds the sidebar items of every content type and taxonomy. Running it again only replaces them.
class ContentAdminNav
{
    // Content types from plugins are placed after this order, so they come after Media and Comments.
    public const PLUGIN_TYPE_ORDER = 100;

    // Extra search words for the core content types.
    protected const NAV_KEYWORDS = [
        'post' => ['blog', 'articles', 'news', 'entradas', 'artículos'],
        'page' => ['static pages', 'páginas'],
        'category' => ['categorías'],
        'tag' => ['labels', 'etiquetas'],
    ];

    public function __construct(
        protected ContentTypeRegistry $types,
        protected TaxonomyRegistry $taxonomies,
    ) {}

    // Content types go in the Content group. Types from plugins come after Media and Comments.
    // A taxonomy is shown inside the first content type that uses it, like Categories inside Posts.
    public function register(): void
    {
        $types = $this->types;

        foreach ($types->all() as $type) {
            $plugin = $types->pluginOf($type->name);

            AdminNav::add(new NavItem(
                key: 'entries.'.$type->name,
                label: $type->pluralLabel,
                route: 'admin.entries.index',
                icon: $type->icon,
                order: $plugin === null ? $type->menuOrder : self::PLUGIN_TYPE_ORDER + $type->menuOrder,
                capability: 'viewAny',
                parameters: ['contentType' => $type->name],
                keywords: [$type->name, strtolower($type->label), ...(self::NAV_KEYWORDS[$type->name] ?? [])],
                plugin: $plugin,
                abilityArguments: [Entry::class, $type->name],
            ));
        }

        foreach ($this->taxonomies->all() as $taxonomy) {
            $owner = collect($taxonomy->contentTypes)->first(fn (string $name): bool => $types->has($name));

            AdminNav::add(new NavItem(
                key: 'terms.'.$taxonomy->name,
                label: $taxonomy->pluralLabel,
                route: 'admin.terms.index',
                icon: $taxonomy->icon,
                order: $taxonomy->menuOrder,
                capability: Capability::ManageTerms,
                parameters: ['taxonomy' => $taxonomy->name],
                parent: $owner === null ? null : 'entries.'.$owner,
                keywords: [$taxonomy->name, strtolower($taxonomy->label), ...(self::NAV_KEYWORDS[$taxonomy->name] ?? [])],
                plugin: $owner === null ? null : $types->pluginOf($owner),
            ));
        }
    }
}
