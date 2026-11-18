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

// Questions or parts that open and close, built on <details>, so it works without JavaScript.
// With "one at a time", opening one closes the others (the details "name" attribute).
class AccordionBlock extends ModuleBlock implements CacheableBlock, HasStarterBlocks, RestrictsChildBlocks
{
    public const NAME = 'accordion';

    // Attr given to each item while it is drawn: the group name when only one may be open.
    public const GROUP = 'verso:group';

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return __('Accordion');
    }

    public function description(): string
    {
        return __('Parts that open and close, like questions and answers.');
    }

    public function starter(): array
    {
        return [
            ['type' => AccordionItemBlock::NAME, 'attrs' => ['title' => __('First question')], 'children' => [['type' => 'paragraph', 'content' => __('Write the answer here.')]]],
            ['type' => AccordionItemBlock::NAME, 'attrs' => ['title' => __('Second question')], 'children' => [['type' => 'paragraph', 'content' => __('Write the answer here.')]]],
        ];
    }

    public function childTypes(): array
    {
        return [AccordionItemBlock::NAME];
    }

    public function acceptsChild(string $type): bool
    {
        return $type === AccordionItemBlock::NAME;
    }

    public function fields(): array
    {
        return [
            new BlockField('single', __('Only one open at a time'), BlockFieldType::Toggle, false, help: __('Easier to scan, but people can not compare two answers.')),
        ];
    }

    public function assets(): array
    {
        return [ToggleBlock::ASSET];
    }

    protected function html(BlockData $block, BlockRenderer $renderer): string
    {
        $items = '';

        foreach ($this->childrenOf($block, AccordionItemBlock::NAME) as $item) {
            if ($block->bool('single')) {
                $item = $this->withAttr($item, self::GROUP, $this->domId($block));
            }

            $items .= $this->child($renderer, $item);
        }

        return $items === '' ? '' : '<div class="verso-accordion">'.$items.'</div>';
    }
}
