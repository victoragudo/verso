<?php

namespace App\Verso\Editor\Builder;

/**
 * Keeps the block categories of the "Add" panel.
 *
 * @extends Registry<BlockCategory>
 */
class BlockCategoryRegistry extends Registry
{
    public function register(BlockCategory $category): void
    {
        $this->put($category->key, $category);
    }

    /**
     * Every category, sorted by order and then by label.
     *
     * @return list<BlockCategory>
     */
    public function all(): array
    {
        $categories = array_values($this->items);
        usort($categories, fn (BlockCategory $a, BlockCategory $b): int => [$a->order, $a->label] <=> [$b->order, $b->label]);

        return $categories;
    }

    /** @return list<array{key: string, label: string, order: int}> */
    public function toArray(): array
    {
        return array_map(fn (BlockCategory $category): array => $category->toArray(), $this->all());
    }
}
