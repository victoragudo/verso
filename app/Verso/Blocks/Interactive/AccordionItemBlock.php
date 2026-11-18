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

// One part of an accordion: a summary line and the blocks that show when it opens.
class AccordionItemBlock extends ModuleBlock implements CacheableBlock, HasChildBlocks, HasParentBlocks
{
    public const NAME = 'accordion-item';

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return __('Accordion item');
    }

    public function description(): string
    {
        return __('A title and the blocks that show when it opens.');
    }

    public function parents(): array
    {
        return [AccordionBlock::NAME];
    }

    public function acceptsChild(string $type): bool
    {
        return ! in_array($type, ['column', self::NAME], true);
    }

    public function fields(): array
    {
        return [
            new BlockField('title', __('Title'), BlockFieldType::Text, '', required: true, max: 200),
            new BlockField('open', __('Open when the page loads'), BlockFieldType::Toggle, false),
        ];
    }

    public function assets(): array
    {
        return [ToggleBlock::ASSET];
    }

    protected function html(BlockData $block, BlockRenderer $renderer): string
    {
        $group = is_string($block->attrs[AccordionBlock::GROUP] ?? null) ? $block->attrs[AccordionBlock::GROUP] : null;

        return ToggleBlock::details($this->text($block, 'title', __('More')), $renderer->renderChildren($block), $block->bool('open'), $group);
    }
}
