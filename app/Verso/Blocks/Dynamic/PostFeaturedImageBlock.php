<?php

namespace App\Verso\Blocks\Dynamic;

use App\Verso\Content\Models\Entry;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Enums\BlockFieldType;
use Illuminate\Support\Facades\Blade;

// The featured image of the current entry, with its width and height so the page does not move.
class PostFeaturedImageBlock extends DynamicBlock
{
    public function name(): string
    {
        return 'post-featured-image';
    }

    public function label(): string
    {
        return __('Featured image');
    }

    public function description(): string
    {
        return __('The featured image of the post or page being shown.');
    }

    public function fields(): array
    {
        return [
            new BlockField('link', __('Link to the entry'), BlockFieldType::Toggle, false),
            new BlockField('eager', __('Load right away (for the first picture of the page)'), BlockFieldType::Toggle, false),
        ];
    }

    protected function forEntry(Entry $entry, BlockData $block, BlockRenderer $renderer): string
    {
        $html = trim(Blade::render('<x-verso-featured-image :entry="$entry" class="verso-post-image" :eager="$eager" />', ['entry' => $entry, 'eager' => $block->bool('eager')]));

        return $html === '' ? '' : '<div class="verso-post-featured">'.$this->linked($entry, $html, $block->bool('link')).'</div>';
    }
}
