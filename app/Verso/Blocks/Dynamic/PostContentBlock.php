<?php

namespace App\Verso\Blocks\Dynamic;

use App\Verso\Content\Models\Entry;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Plugins\ContentRenderer;

// The blocks of the current entry. An entry never draws itself twice, so a loop that shows itself stops.
class PostContentBlock extends DynamicBlock
{
    /** @var array<int, true> entries being drawn by this block */
    protected array $drawing = [];

    public function name(): string
    {
        return 'post-content';
    }

    public function label(): string
    {
        return __('Content of the entry');
    }

    public function description(): string
    {
        return __('The text and blocks of the post or page being shown.');
    }

    protected function forEntry(Entry $entry, BlockData $block, BlockRenderer $renderer): string
    {
        if (isset($this->drawing[$entry->id])) {
            return '';
        }

        $this->drawing[$entry->id] = true;

        try {
            return '<div class="verso-post-content prose">'.app(ContentRenderer::class)->render($entry).'</div>';
        } finally {
            unset($this->drawing[$entry->id]);
        }
    }
}
