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

// One tab of a tabs block: its name and the blocks of its panel.
class TabBlock extends ModuleBlock implements CacheableBlock, HasChildBlocks, HasParentBlocks
{
    public const NAME = 'tab';

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return __('Tab');
    }

    public function description(): string
    {
        return __('One tab with its own blocks.');
    }

    public function parents(): array
    {
        return [TabsBlock::NAME];
    }

    public function acceptsChild(string $type): bool
    {
        return ! in_array($type, ['column', self::NAME], true);
    }

    public function fields(): array
    {
        return [new BlockField('title', __('Tab name'), BlockFieldType::Text, '', required: true, max: 80)];
    }

    public function assets(): array
    {
        return [TabsBlock::NAME];
    }

    protected function html(BlockData $block, BlockRenderer $renderer): string
    {
        $index = is_int($block->attrs[TabsBlock::INDEX] ?? null) ? $block->attrs[TabsBlock::INDEX] : 0;

        return '<div class="verso-tabs__panel" role="tabpanel" id="'.$this->domId($block, 'panel').'" aria-labelledby="'.$this->domId($block, 'tab').'"'
            .($index === 0 ? '' : ' hidden="until-found"').'><div class="verso-tabs__inner">'.$renderer->renderChildren($block).'</div></div>';
    }
}
