<?php

namespace App\Verso\Blocks\Interactive;

use App\Verso\Blocks\Support\ModuleBlock;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Contracts\CacheableBlock;
use App\Verso\Plugins\Contracts\HasChildBlocks;
use App\Verso\Plugins\Contracts\HasStarterBlocks;
use App\Verso\Plugins\Enums\BlockFieldType;

// The mobile menu designer: a "Menu" button that opens a drawer with any blocks (menus, buttons, social links,
// a search). A native modal dialog, so focus, Escape and the backdrop work. It shows only on small screens.
class MobileMenuBlock extends ModuleBlock implements CacheableBlock, HasChildBlocks, HasStarterBlocks
{
    public const NAME = 'mobile-menu';

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return __('Mobile menu');
    }

    public function description(): string
    {
        return __('A menu button for phones that opens a drawer with any blocks.');
    }

    public function category(): string
    {
        return 'site';
    }

    public function acceptsChild(string $type): bool
    {
        return ! in_array($type, ['column', self::NAME], true);
    }

    public function starter(): array
    {
        return [
            ['type' => 'navigation', 'attrs' => ['collapse' => false]],
            ['type' => 'social-links'],
        ];
    }

    public function fields(): array
    {
        return [
            new BlockField('label', __('Button text'), BlockFieldType::Text, '', help: __('Like "Menu".'), max: 40),
            new BlockField('side', __('Drawer comes from'), BlockFieldType::Select, 'end', ['start' => __('The start side'), 'end' => __('The end side'), 'top' => __('The top, full width')]),
            new BlockField('showOn', __('Show the button on'), BlockFieldType::Select, 'phone', ['phone' => __('Phones'), 'tablet' => __('Phones and tablets'), 'always' => __('Every screen')]),
        ];
    }

    protected function html(BlockData $block, BlockRenderer $renderer): string
    {
        $id = $this->domId($block, 'drawer');
        $label = $this->text($block, 'label', __('Menu'));

        return '<div class="verso-mobile-menu verso-mobile-menu--'.$this->choice($block, 'showOn', ['phone', 'tablet', 'always'], 'phone').'" data-verso-mobile-menu>'
            .'<button type="button" class="verso-mobile-menu__button" commandfor="'.$id.'" command="show-modal" aria-haspopup="dialog">'
            .$this->icon('menu').'<span>'.e($label).'</span></button>'
            .'<dialog class="verso-mobile-menu__drawer verso-mobile-menu__drawer--'.$this->choice($block, 'side', ['start', 'end', 'top'], 'end').'" id="'.$id.'" aria-label="'.e($label).'" closedby="any">'
            .'<button type="button" class="verso-mobile-menu__close" commandfor="'.$id.'" command="close" autofocus><span class="verso-visually-hidden">'.e(__('Close the menu')).'</span>'.$this->icon('x').'</button>'
            .'<div class="verso-mobile-menu__body">'.$renderer->renderChildren($block).'</div></dialog></div>';
    }
}
