<?php

namespace App\Verso\Blocks\Interactive;

use App\Verso\Blocks\Support\ModuleBlock;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Plugins\Contracts\CacheableBlock;
use App\Verso\Plugins\Contracts\HasStarterBlocks;
use App\Verso\Plugins\Contracts\RestrictsChildBlocks;

// Steps or dates in order on a line, like the story of a business. An ordered list, no JavaScript.
class TimelineBlock extends ModuleBlock implements CacheableBlock, HasStarterBlocks, RestrictsChildBlocks
{
    public const NAME = 'timeline';

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return __('Timeline');
    }

    public function description(): string
    {
        return __('Dates or steps in order on a line.');
    }

    public function category(): string
    {
        return 'text';
    }

    public function starter(): array
    {
        return [
            ['type' => TimelineItemBlock::NAME, 'attrs' => ['when' => '2024', 'title' => __('First step')], 'children' => [['type' => 'paragraph', 'content' => __('What happened.')]]],
            ['type' => TimelineItemBlock::NAME, 'attrs' => ['when' => '2025', 'title' => __('Next step')], 'children' => [['type' => 'paragraph', 'content' => __('What happened.')]]],
        ];
    }

    public function childTypes(): array
    {
        return [TimelineItemBlock::NAME];
    }

    public function acceptsChild(string $type): bool
    {
        return $type === TimelineItemBlock::NAME;
    }

    protected function html(BlockData $block, BlockRenderer $renderer): string
    {
        $items = $renderer->renderChildren($block);

        return $items === '' ? '' : '<ol class="verso-timeline">'.$items.'</ol>';
    }
}
