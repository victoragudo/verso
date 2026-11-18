<?php

namespace App\Verso\Site\Menus;

use App\Verso\Site\Enums\MenuItemVisibility;
use App\Verso\Site\Models\Menu;
use App\Verso\Site\Models\MenuItem;
use Illuminate\Database\Eloquent\Collection;

/**
 * Turns the menu of a theme location into plain links for the public site.
 *
 * @phpstan-type MenuLink array{label: string, url: string, new_tab: bool, active: bool, mega?: bool, rel?: string|null, visibility?: string, children: list<mixed>}
 */
class MenuRenderer
{
    /** @var list<string> */
    public const RELATIONS = ['entry.parent', 'term'];

    public function __construct(protected MenuCache $cache) {}

    /**
     * Visible links of the location, with nested children. Items whose target is gone are left out with their children.
     *
     * @return list<MenuLink>
     */
    public function links(string $location, ?string $currentUrl = null): array
    {
        /** @var list<MenuLink> $links */
        $links = $this->cached($location)['links'];

        return $this->markActive($this->visible($links, auth()->check()), $currentUrl === null ? null : rtrim($currentUrl, '/'));
    }

    // True when a menu with at least one item is assigned to the location.
    public function hasItems(string $location): bool
    {
        return $this->cached($location)['has_items'] === true;
    }

    /**
     * The cached tree of a location, built from the database when missing.
     *
     * @return array<string, mixed>
     */
    protected function cached(string $location): array
    {
        return $this->cache->remember($location, url('/'), function () use ($location): array {
            $menu = Menu::forLocation($location);
            $items = $menu?->tree(self::RELATIONS) ?? new Collection;

            return ['has_items' => $items->isNotEmpty(), 'links' => $this->build($items)];
        });
    }

    /**
     * @param  Collection<int, MenuItem>  $items
     * @return list<MenuLink>
     */
    protected function build(Collection $items): array
    {
        $links = [];

        foreach ($items as $item) {
            $url = $item->resolvedUrl();

            if ($url === null) {
                continue;
            }

            $links[] = [
                'label' => $item->resolvedLabel(),
                'url' => $url,
                'new_tab' => $item->open_in_new_tab,
                'active' => false,
                'mega' => $item->mega,
                'rel' => $item->rel,
                'visibility' => $item->visibility->value,
                'children' => $this->build($item->children),
            ];
        }

        return $links;
    }

    // The rel attribute value of a link: its rel words, plus "noopener" when it opens a new tab.
    /** @param array<string, mixed> $link */
    public static function rel(array $link): ?string
    {
        $words = array_filter([is_string($link['rel'] ?? null) ? $link['rel'] : null, ($link['new_tab'] ?? false) === true ? 'noopener' : null]);

        return $words === [] ? null : implode(' ', $words);
    }

    /**
     * Drops the links the visitor may not see (members only or guests only), with their children.
     * Links cached by an older Verso have no visibility and are shown to everyone.
     *
     * @param  list<MenuLink>  $links
     * @return list<MenuLink>
     */
    protected function visible(array $links, bool $signedIn): array
    {
        $visible = [];

        foreach ($links as $link) {
            if (! (MenuItemVisibility::tryFrom((string) ($link['visibility'] ?? '')) ?? MenuItemVisibility::Everyone)->allows($signedIn)) {
                continue;
            }

            /** @var list<MenuLink> $children */
            $children = $link['children'];
            $link['children'] = $this->visible($children, $signedIn);
            $visible[] = $link;
        }

        return $visible;
    }

    /**
     * Marks the links that point to the current page.
     *
     * @param  list<MenuLink>  $links
     * @return list<MenuLink>
     */
    protected function markActive(array $links, ?string $currentUrl): array
    {
        foreach ($links as $index => $link) {
            /** @var list<MenuLink> $children */
            $children = $link['children'];
            $links[$index]['active'] = $currentUrl !== null && rtrim($link['url'], '/') === $currentUrl;
            $links[$index]['children'] = $this->markActive($children, $currentUrl);
        }

        return $links;
    }
}
