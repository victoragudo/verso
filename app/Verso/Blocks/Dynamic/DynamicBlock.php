<?php

namespace App\Verso\Blocks\Dynamic;

use App\Verso\Blocks\Support\ModuleBlock;
use App\Verso\Content\Models\Entry;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Editor\Support\RenderContext;

// Base of the blocks that show data of the current entry (title, date, author...). Inside a query loop the
// current entry is the one of the loop item. Outside of any entry they draw nothing.
abstract class DynamicBlock extends ModuleBlock
{
    public function category(): string
    {
        return 'dynamic';
    }

    public function assets(): array
    {
        return [];
    }

    // The HTML for one entry. Blocks that read the page instead (lists, breadcrumbs) change html() instead.
    protected function forEntry(Entry $entry, BlockData $block, BlockRenderer $renderer): string
    {
        return '';
    }

    protected function html(BlockData $block, BlockRenderer $renderer): string
    {
        $entry = $this->entry();

        return $entry === null ? '' : $this->forEntry($entry, $block, $renderer);
    }

    protected function entry(): ?Entry
    {
        return app(RenderContext::class)->entry() ?? (app(RenderContext::class)->subject() instanceof Entry ? app(RenderContext::class)->subject() : null);
    }

    // Wraps HTML in a link to the entry when asked.
    protected function linked(Entry $entry, string $html, bool $link): string
    {
        return $link ? '<a href="'.e($entry->url()).'">'.$html.'</a>' : $html;
    }
}
