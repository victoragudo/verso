<?php

namespace App\Verso\Plugins\Admin;

use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Core\Admin\AdminNavRegistry;
use App\Verso\Core\Admin\NavGroup;
use App\Verso\Core\Admin\NavItem;
use App\Verso\I18n\Translate;
use App\Verso\Plugins\Plugin;
use App\Verso\Plugins\Support\PluginTranslations;

// Puts the admin pages of a plugin in the sidebar. Core decides the place, so plugins
// cannot add new groups or spread their pages over several groups.
class PluginNavPlacement
{
    // Order of plugin items inside a core group: after the core items.
    public const PLUGIN_ORDER = 100;

    // Gap between the orders of two links of one plugin.
    protected const ITEM_STEP = 10;

    public function __construct(
        protected AdminNavRegistry $nav,
        protected ContentTypeRegistry $types,
        protected PluginTranslations $texts,
    ) {}

    // Key of the parent item that holds the links of a plugin.
    public static function parentKey(string $slug): string
    {
        return 'plugin.'.$slug;
    }

    // Add the links from the "admin" part of verso.json.
    public function register(Plugin $plugin): void
    {
        $menu = $plugin->manifest?->admin;

        if ($menu === null) {
            return;
        }

        $parent = $this->parentFor($plugin, $menu);

        foreach ($menu->items as $index => $item) {
            $single = $parent === null && $menu->group !== NavGroup::Dashboard;

            $this->nav->add(new NavItem(
                key: $plugin->slug.'.'.$item->key,
                label: $item->label,
                route: $item->route,
                icon: $item->icon ?? $menu->icon ?? 'plugins',
                order: $single ? self::PLUGIN_ORDER : ($index + 1) * self::ITEM_STEP,
                capability: $item->capability,
                group: $menu->group,
                parent: $menu->group === NavGroup::Dashboard ? 'dashboard' : $parent,
                keywords: $single ? [...$item->keywords, ...$menu->keywords] : $item->keywords,
                plugin: $plugin->slug,
            ));
        }
    }

    // A nav item a plugin adds in PHP. It goes to the place the plugin declared, or under Tools.
    public function place(Plugin $plugin, NavItem $item): NavItem
    {
        $menu = $plugin->manifest->admin ?? new PluginAdminMenu(NavGroup::Tools, null, $item->icon, []);

        if ($menu->group === NavGroup::Dashboard) {
            return $item->placedIn(NavGroup::Dashboard, 'dashboard', $plugin->slug);
        }

        // Without an "admin" part the plugin gets its own item under Tools.
        $parent = $menu->hasParent() || $plugin->manifest?->admin === null ? $this->parentFor($plugin, $menu, force: true) : null;

        return $item->placedIn($menu->group, $parent, $plugin->slug);
    }

    /**
     * Where the plugin is in the admin, like "Tools > Shop". Null when it adds no admin page.
     *
     * @return array{path: list<string>, key: string}|null
     */
    public function location(Plugin $plugin): ?array
    {
        $menu = $plugin->manifest?->admin;

        if ($menu !== null && $menu->items !== []) {
            $first = $this->texts->text($plugin, $menu->items[0]->label);

            return match (true) {
                $menu->group === NavGroup::Dashboard => [
                    'path' => [$this->groupLabel(NavGroup::Dashboard), $first],
                    'key' => $plugin->slug.'.'.$menu->items[0]->key,
                ],
                $menu->hasParent() => [
                    'path' => [$this->groupLabel($menu->group), $this->texts->text($plugin, $this->label($plugin, $menu))],
                    'key' => self::parentKey($plugin->slug),
                ],
                default => [
                    'path' => [$this->groupLabel($menu->group), $first],
                    'key' => $plugin->slug.'.'.$menu->items[0]->key,
                ],
            };
        }

        foreach ($this->nav->all() as $item) {
            if ($item->plugin === $plugin->slug && $item->parent === null) {
                return ['path' => [$this->groupLabel($item->group), $this->texts->text($plugin, $item->label)], 'key' => $item->key];
            }
        }

        return null;
    }

    protected function groupLabel(NavGroup $group): string
    {
        return Translate::text($group->label());
    }

    // The parent item for the plugin links, added when needed. Null when the plugin has one link outside Tools.
    protected function parentFor(Plugin $plugin, PluginAdminMenu $menu, bool $force = false): ?string
    {
        if (! $force && ! $menu->hasParent()) {
            return null;
        }

        $key = self::parentKey($plugin->slug);

        if (! $this->nav->has($key)) {
            $this->nav->add(new NavItem(
                key: $key,
                label: $this->label($plugin, $menu),
                route: '',
                icon: $menu->icon ?? 'plugins',
                order: self::PLUGIN_ORDER,
                group: $menu->group,
                keywords: [$plugin->slug, ...$menu->keywords],
                plugin: $plugin->slug,
            ));
        }

        return $key;
    }

    protected function label(Plugin $plugin, PluginAdminMenu $menu): string
    {
        return $menu->label ?? $plugin->manifest->name ?? $plugin->slug;
    }
}
