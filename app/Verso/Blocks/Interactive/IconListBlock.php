<?php

namespace App\Verso\Blocks\Interactive;

use App\Verso\Blocks\Support\ModuleBlock;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Contracts\CacheableBlock;
use App\Verso\Plugins\Contracts\HasStarterBlocks;
use App\Verso\Plugins\Contracts\RestrictsChildBlocks;
use App\Verso\Plugins\Enums\BlockFieldType;

// A list where each line starts with an icon, like "Free delivery" with a truck.
class IconListBlock extends ModuleBlock implements CacheableBlock, HasStarterBlocks, RestrictsChildBlocks
{
    public const NAME = 'icon-list';

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return __('Icon list');
    }

    public function description(): string
    {
        return __('A list where each line starts with an icon.');
    }

    public function category(): string
    {
        return 'text';
    }

    public function starter(): array
    {
        return [
            ['type' => IconListItemBlock::NAME, 'attrs' => ['icon' => 'check', 'text' => __('First point')]],
            ['type' => IconListItemBlock::NAME, 'attrs' => ['icon' => 'check', 'text' => __('Second point')]],
            ['type' => IconListItemBlock::NAME, 'attrs' => ['icon' => 'check', 'text' => __('Third point')]],
        ];
    }

    public function childTypes(): array
    {
        return [IconListItemBlock::NAME];
    }

    public function acceptsChild(string $type): bool
    {
        return $type === IconListItemBlock::NAME;
    }

    public function fields(): array
    {
        return [
            new BlockField('layout', __('Layout'), BlockFieldType::Select, 'stacked', ['stacked' => __('One under the other'), 'inline' => __('On one line')]),
        ];
    }

    protected function html(BlockData $block, BlockRenderer $renderer): string
    {
        $items = $renderer->renderChildren($block);

        return $items === '' ? '' : '<ul class="verso-icon-list verso-icon-list--'.$this->choice($block, 'layout', ['stacked', 'inline'], 'stacked').'">'.$items.'</ul>';
    }

    public function assets(): array
    {
        return ['icons'];
    }
}
