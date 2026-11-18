<?php

namespace App\Verso\Core\Admin;

use App\Models\User;
use App\Verso\I18n\Translate;
use App\Verso\Plugins\Filters\AdminNavFilter;

// Keeps the list of admin sidebar items that modules register.
class AdminNavRegistry
{
    /** @var array<string, NavItem> */
    protected array $items = [];

    // Add an item. An item with the same key replaces the old one.
    public function add(NavItem $item): void
    {
        $this->items[$item->key] = $item;
    }

    public function remove(string $key): void
    {
        unset($this->items[$key]);
    }

    public function has(string $key): bool
    {
        return isset($this->items[$key]);
    }

    public function get(string $key): ?NavItem
    {
        return $this->items[$key] ?? null;
    }

    /**
     * All items sorted by group, then order, then label.
     *
     * @return list<NavItem>
     */
    public function all(): array
    {
        $items = array_values($this->items);

        usort($items, fn (NavItem $a, NavItem $b): int => [$a->group->position(), $a->order, $a->label] <=> [$b->group->position(), $b->order, $b->label]);

        return $items;
    }

    /**
     * The items this user can see, ready for the UI, as a flat list. Children point to their parent with "parent".
     * Plugins can change the items with the AdminNavFilter.
     *
     * @return list<array{key: string, label: string, href: string, icon: string, group: string, parent: string|null, keywords: list<string>, plugin: string|null}>
     */
    public function forUser(?User $user): array
    {
        /** @var list<NavItem> $visible */
        $visible = AdminNavFilter::apply(
            array_values(array_filter($this->all(), fn (NavItem $item): bool => $item->visibleTo($user))),
            $user,
        );

        $byKey = [];
        foreach ($visible as $item) {
            $byKey[$item->key] = $item;
        }

        $firstChildHref = [];
        $rows = [];

        foreach ($visible as $item) {
            // A child whose parent is hidden moves up to the top of its group.
            if ($item->parent !== null && ! isset($byKey[$item->parent])) {
                $item = $item->withParent(null);
            }

            if ($item->parent !== null && ! $item->isGroupOnly()) {
                $firstChildHref[$item->parent] ??= (string) $item->href();
            }

            $rows[] = $item;
        }

        $result = [];

        foreach ($rows as $item) {
            if ($item->isGroupOnly()) {
                // A group item with no visible child has nothing to open.
                if (! isset($firstChildHref[$item->key])) {
                    continue;
                }

                $result[] = $item->toArray($firstChildHref[$item->key]);

                continue;
            }

            $result[] = $item->toArray();
        }

        return $this->treeOrder($result);
    }

    /**
     * Puts each item right after its parent, so the flat list reads like the sidebar.
     *
     * @param  list<array{key: string, label: string, href: string, icon: string, group: string, parent: string|null, keywords: list<string>, plugin: string|null}>  $rows
     * @return list<array{key: string, label: string, href: string, icon: string, group: string, parent: string|null, keywords: list<string>, plugin: string|null}>
     */
    protected function treeOrder(array $rows): array
    {
        $children = [];
        foreach ($rows as $row) {
            if ($row['parent'] !== null) {
                $children[$row['parent']][] = $row;
            }
        }

        $ordered = [];

        foreach ($rows as $row) {
            if ($row['parent'] === null) {
                $this->appendWithChildren($row, $children, $ordered);
            }
        }

        return $ordered;
    }

    /**
     * @param  array{key: string, label: string, href: string, icon: string, group: string, parent: string|null, keywords: list<string>, plugin: string|null}  $row
     * @param  array<string, list<array{key: string, label: string, href: string, icon: string, group: string, parent: string|null, keywords: list<string>, plugin: string|null}>>  $children
     * @param  list<array{key: string, label: string, href: string, icon: string, group: string, parent: string|null, keywords: list<string>, plugin: string|null}>  $ordered
     */
    protected function appendWithChildren(array $row, array $children, array &$ordered): void
    {
        $ordered[] = $row;

        foreach ($children[$row['key']] ?? [] as $child) {
            $this->appendWithChildren($child, $children, $ordered);
        }
    }

    /**
     * The labels from the group down to the item, like ["Tools", "Shop", "Orders"].
     *
     * @return list<string>
     */
    public function path(string $key): array
    {
        $item = $this->items[$key] ?? null;

        if ($item === null) {
            return [];
        }

        $labels = [$item->translatedLabel()];
        $seen = [$key => true];

        while ($item->parent !== null && isset($this->items[$item->parent]) && ! isset($seen[$item->parent])) {
            $seen[$item->parent] = true;
            $item = $this->items[$item->parent];
            array_unshift($labels, $item->translatedLabel());
        }

        if ($item->group !== NavGroup::Dashboard || $item->key !== 'dashboard') {
            array_unshift($labels, Translate::text($item->group->label()));
        }

        return $labels;
    }
}
