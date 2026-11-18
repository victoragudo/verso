<?php

namespace App\Verso\Blocks\Interactive;

use App\Verso\Blocks\Support\ModuleBlock;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Plugins\Contracts\CacheableBlock;
use App\Verso\Plugins\Contracts\HasChildBlocks;
use App\Verso\Plugins\Contracts\HasParentBlocks;

// One slide of a slider. Any blocks fit inside.
class SlideBlock extends ModuleBlock implements CacheableBlock, HasChildBlocks, HasParentBlocks
{
    public const NAME = 'slide';

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return __('Slide');
    }

    public function description(): string
    {
        return __('One slide with its own blocks.');
    }

    public function parents(): array
    {
        return [SliderBlock::NAME];
    }

    public function acceptsChild(string $type): bool
    {
        return ! in_array($type, ['column', self::NAME], true);
    }

    public function assets(): array
    {
        return [SliderBlock::NAME];
    }

    protected function html(BlockData $block, BlockRenderer $renderer): string
    {
        $position = is_int($block->attrs[SliderBlock::POSITION] ?? null) ? $block->attrs[SliderBlock::POSITION] : 1;
        $count = is_int($block->attrs[SliderBlock::COUNT] ?? null) ? $block->attrs[SliderBlock::COUNT] : 1;

        return '<div class="verso-slider__slide" role="group" aria-roledescription="'.e(__('slide')).'"'
            .' aria-label="'.e(__(':number of :count', ['number' => $position, 'count' => $count])).'"'
            .' data-marker-name="'.e(__('Go to slide :number', ['number' => $position])).'">'.$renderer->renderChildren($block).'</div>';
    }
}
