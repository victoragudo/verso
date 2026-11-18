<?php

namespace App\Verso\Blocks\Dynamic;

use App\Verso\Content\Models\Entry;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Enums\BlockFieldType;

// The title of the current entry, as a heading.
class PostTitleBlock extends DynamicBlock
{
    public function name(): string
    {
        return 'post-title';
    }

    public function label(): string
    {
        return __('Title of the entry');
    }

    public function description(): string
    {
        return __('The title of the post or page being shown.');
    }

    public function fields(): array
    {
        return [
            new BlockField('level', __('Heading level'), BlockFieldType::Select, '2', ['1' => 'H1', '2' => 'H2', '3' => 'H3', '4' => 'H4', 'p' => __('Plain text')]),
            new BlockField('link', __('Link to the entry'), BlockFieldType::Toggle, false),
        ];
    }

    protected function forEntry(Entry $entry, BlockData $block, BlockRenderer $renderer): string
    {
        $level = $this->choice($block, 'level', ['1', '2', '3', '4', 'p'], '2');
        $tag = $level === 'p' ? 'p' : 'h'.$level;

        return '<'.$tag.' class="verso-post-title">'.$this->linked($entry, e($entry->title), $block->bool('link')).'</'.$tag.'>';
    }
}
