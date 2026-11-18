<?php

namespace App\Verso\Blocks\Interactive;

use App\Verso\Blocks\Support\ModuleBlock;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Contracts\CacheableBlock;
use App\Verso\Plugins\Contracts\HasChildBlocks;
use App\Verso\Plugins\Enums\BlockFieldType;

// A menu entry that opens a big panel with any blocks: columns of links, pictures, a button.
// Site navigation, not an app menu: a button with aria-expanded and a popover panel; links stay in the Tab order.
// On wide screens with a mouse it also opens on hover. Without JavaScript the panel shows under the button.
class MegaMenuBlock extends ModuleBlock implements CacheableBlock, HasChildBlocks
{
    public const NAME = 'mega-menu';

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return __('Mega menu');
    }

    public function description(): string
    {
        return __('A menu button that opens a big panel with any blocks inside.');
    }

    public function category(): string
    {
        return 'site';
    }

    public function acceptsChild(string $type): bool
    {
        return ! in_array($type, ['column', self::NAME, MobileMenuBlock::NAME], true);
    }

    public function fields(): array
    {
        return [
            new BlockField('label', __('Menu text'), BlockFieldType::Text, '', required: true, max: 60),
            new BlockField('width', __('Panel width'), BlockFieldType::Select, 'wide', ['narrow' => __('Narrow'), 'wide' => __('Wide'), 'full' => __('Full width')]),
            new BlockField('hover', __('Open when the mouse points at it'), BlockFieldType::Toggle, true),
        ];
    }

    protected function html(BlockData $block, BlockRenderer $renderer): string
    {
        $id = $this->domId($block, 'panel');

        return '<div class="verso-mega" data-verso-mega'.($block->bool('hover', true) ? ' data-hover' : '').'>'
            .'<button type="button" class="verso-mega__button" aria-expanded="false" aria-controls="'.$id.'" popovertarget="'.$id.'">'
            .e($this->text($block, 'label', __('Menu'))).$this->icon('chevron-down', '', 'verso-icon verso-mega__chevron').'</button>'
            .'<div class="verso-mega__panel verso-mega__panel--'.$this->choice($block, 'width', ['narrow', 'wide', 'full'], 'wide').'" id="'.$id.'" popover="auto">'
            .$renderer->renderChildren($block).'</div></div>';
    }
}
