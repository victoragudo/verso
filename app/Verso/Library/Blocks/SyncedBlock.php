<?php

namespace App\Verso\Library\Blocks;

use App\Verso\Blocks\Support\ModuleBlock;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Library\Models\LibraryItem;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Enums\BlockFieldType;

// A synced block: it points to a library item and draws its blocks. Change the item once and every page
// that uses it changes. The usage index records where it is used (key attr "ref"), so caches of those pages
// are emptied when it changes.
class SyncedBlock extends ModuleBlock
{
    public const NAME = 'synced';

    /** @var array<int, LibraryItem|null> items read in this request */
    protected array $items = [];

    /** @var array<int, true> items being drawn, so an item that holds itself stops */
    protected array $drawing = [];

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return __('Synced block');
    }

    public function description(): string
    {
        return __('Blocks from the library that look the same everywhere they are used.');
    }

    public function category(): string
    {
        return 'advanced';
    }

    public function keyAttribute(): ?string
    {
        return 'ref';
    }

    public function assets(): array
    {
        return [];
    }

    public function fields(): array
    {
        $items = [];

        foreach (LibraryItem::query()->where('kind', LibraryItem::SYNCED)->orderBy('title')->limit((int) config('verso.library.editor_items'))->get(['id', 'title']) as $item) {
            $items[(string) $item->id] = $item->title;
        }

        return [new BlockField('ref', __('Synced block'), BlockFieldType::Select, array_key_first($items) ?? '', $items === [] ? ['' => __('Save blocks to the library first')] : $items, required: true, searchable: true)];
    }

    protected function html(BlockData $block, BlockRenderer $renderer): string
    {
        $id = is_numeric($block->attrs['ref'] ?? null) ? (int) $block->attrs['ref'] : 0;
        $item = $this->item($id);

        if ($item === null || isset($this->drawing[$id])) {
            return '';
        }

        $this->drawing[$id] = true;
        $html = '';

        try {
            foreach ($item->blocks as $child) {
                if (is_array($child)) {
                    $html .= $renderer->renderBlock($child, $block->depth + 1);
                }
            }
        } finally {
            unset($this->drawing[$id]);
        }

        return $html === '' ? '' : '<div class="verso-synced" data-verso-synced="'.$id.'">'.$html.'</div>';
    }

    protected function item(int $id): ?LibraryItem
    {
        if (! array_key_exists($id, $this->items)) {
            $this->items[$id] = $id > 0 ? LibraryItem::query()->where('kind', LibraryItem::SYNCED)->find($id) : null;
        }

        return $this->items[$id];
    }

    // Forgets the items read so far, after an item changes.
    public function forget(): void
    {
        $this->items = [];
    }
}
