<?php

namespace App\Verso\Blocks\Interactive;

use App\Verso\Blocks\Support\ModuleBlock;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Contracts\CacheableBlock;
use App\Verso\Plugins\Contracts\HasChildBlocks;
use App\Verso\Plugins\Contracts\HasParentBlocks;
use App\Verso\Plugins\Enums\BlockFieldType;

// One step of a timeline: a date or step name, a title and any blocks.
class TimelineItemBlock extends ModuleBlock implements CacheableBlock, HasChildBlocks, HasParentBlocks
{
    public const NAME = 'timeline-item';

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return __('Timeline step');
    }

    public function description(): string
    {
        return __('One date or step of a timeline.');
    }

    public function parents(): array
    {
        return [TimelineBlock::NAME];
    }

    public function acceptsChild(string $type): bool
    {
        return ! in_array($type, ['column', self::NAME], true);
    }

    public function fields(): array
    {
        return [
            new BlockField('when', __('Date or step'), BlockFieldType::Text, '', help: __('Like "2019" or "Step 1".'), max: 60),
            new BlockField('title', __('Title'), BlockFieldType::Text, '', required: true, max: 200),
            new BlockField('icon', __('Icon'), BlockFieldType::Icon, null),
        ];
    }

    public function assets(): array
    {
        return [TimelineBlock::NAME];
    }

    protected function html(BlockData $block, BlockRenderer $renderer): string
    {
        $when = $this->text($block, 'when');
        $icon = $this->text($block, 'icon');

        return '<li class="verso-timeline__item"><span class="verso-timeline__marker" aria-hidden="true">'.($icon !== '' ? $this->icon($icon) : '').'</span>'
            .'<div class="verso-timeline__body">'.($when !== '' ? '<p class="verso-timeline__when">'.e($when).'</p>' : '')
            .'<h3 class="verso-timeline__title">'.e($this->text($block, 'title')).'</h3>'.$renderer->renderChildren($block).'</div></li>';
    }
}
