<?php

namespace App\Verso\Site\Actions;

use App\Verso\Site\Enums\MenuItemType;
use App\Verso\Site\Enums\MenuItemVisibility;
use App\Verso\Site\Models\Menu;
use App\Verso\Site\Models\MenuItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Replaces all the items of a menu with a new tree. The order of the list is the menu order.
 *
 * @phpstan-type MenuItemData array{type: string, entry_id?: int|null, term_id?: int|null, label?: string|null, url?: string|null, open_in_new_tab?: bool, mega?: bool, rel?: list<string>|null, visibility?: string|null, children?: list<array<string, mixed>>}
 */
class SyncMenuItems
{
    // Words an editor may add to the rel attribute of a link.
    public const REL_WORDS = ['nofollow', 'sponsored', 'ugc', 'noreferrer'];

    public function __construct(protected int $maxDepth) {}

    /**
     * @param  list<MenuItemData>  $items
     */
    public function handle(Menu $menu, array $items): Menu
    {
        if (self::depthOf($items) > $this->maxDepth) {
            throw new InvalidArgumentException("Menus can be at most {$this->maxDepth} levels deep.");
        }

        DB::transaction(function () use ($menu, $items): void {
            $menu->items()->getQuery()->whereNotNull('parent_id')->delete();
            $menu->items()->getQuery()->delete();

            $this->createItems($menu, $items, null);
            $menu->touch();
        });

        return $menu;
    }

    // How deep a list of item data goes (1 for a flat list).
    /** @param list<array<string, mixed>> $items */
    public static function depthOf(array $items): int
    {
        $deepest = 0;

        foreach ($items as $item) {
            $children = $item['children'] ?? [];
            $deepest = max($deepest, 1 + self::depthOf(is_array($children) ? array_values($children) : []));
        }

        return $deepest;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    protected function createItems(Menu $menu, array $items, ?MenuItem $parent): void
    {
        foreach ($items as $position => $data) {
            $type = MenuItemType::from((string) $data['type']);

            $item = MenuItem::query()->create([
                'menu_id' => $menu->id,
                'parent_id' => $parent?->id,
                'type' => $type,
                'entry_id' => $type === MenuItemType::Entry ? (int) $data['entry_id'] : null,
                'term_id' => $type === MenuItemType::Term ? (int) $data['term_id'] : null,
                'label' => $this->text($data['label'] ?? null),
                'url' => $type === MenuItemType::Custom ? $this->text($data['url'] ?? null) : null,
                'open_in_new_tab' => (bool) ($data['open_in_new_tab'] ?? false),
                'position' => $position,
                'mega' => (bool) ($data['mega'] ?? false),
                'rel' => self::relText($data['rel'] ?? null),
                'visibility' => MenuItemVisibility::tryFrom((string) ($data['visibility'] ?? '')) ?? MenuItemVisibility::Everyone,
            ]);

            $children = $data['children'] ?? [];
            $this->createItems($menu, is_array($children) ? array_values($children) : [], $item);
        }
    }

    // Allowed rel words, in a fixed order, as one text like "nofollow sponsored". Null when none.
    public static function relText(mixed $words): ?string
    {
        $words = is_array($words) ? array_values(array_intersect(self::REL_WORDS, $words)) : [];

        return $words === [] ? null : implode(' ', $words);
    }

    protected function text(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }
}
