<?php

namespace App\Verso\Plugins\Slots;

use App\Verso\I18n\Translate;
use Illuminate\Http\Request;

// Keeps the components plugins add to admin slots and shares them with the admin UI.
class AdminSlotRegistry
{
    /** @var array<string, array<string, SlotItem>> */
    protected array $items = [];

    // Add an item. An item with the same slot and key replaces the old one.
    public function add(SlotItem $item): void
    {
        $this->items[$item->slot->value][$item->key] = $item;
    }

    public function remove(AdminSlot $slot, string $key): void
    {
        unset($this->items[$slot->value][$key]);
    }

    public function has(AdminSlot $slot, string $key): bool
    {
        return isset($this->items[$slot->value][$key]);
    }

    /**
     * The items of one slot, sorted by order, then by key.
     *
     * @return list<SlotItem>
     */
    public function all(AdminSlot $slot): array
    {
        $items = array_values($this->items[$slot->value] ?? []);

        usort($items, fn (SlotItem $a, SlotItem $b): int => [$a->order, $a->key] <=> [$b->order, $b->key]);

        return $items;
    }

    /**
     * The items the current user may see on the current screen, by slot name, ready for the UI.
     *
     * @return array<string, list<array{key: string, component: string, label: string|null, order: int, props: array<string, mixed>}>>
     */
    public function forRequest(Request $request): array
    {
        $user = $request->user();
        $shared = [];

        foreach (AdminSlot::cases() as $slot) {
            if (! $request->routeIs(...$slot->routes())) {
                continue;
            }

            foreach ($this->all($slot) as $item) {
                $props = $item->visibleTo($user) ? $item->resolveProps($request) : null;

                if ($props !== null) {
                    $shared[$slot->value][] = [
                        'key' => $item->key,
                        'component' => $item->component,
                        'label' => $item->label === null ? null : Translate::text($item->label, plugin: $item->plugin),
                        'order' => $item->order,
                        'props' => $props,
                    ];
                }
            }
        }

        return $shared;
    }
}
