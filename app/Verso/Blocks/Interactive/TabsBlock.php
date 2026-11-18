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

// Tabs: a row of buttons, one panel shown at a time. The first panel shows without JavaScript too;
// the script adds arrow keys and "find in page" support. Each tab is a "tab" block with any blocks inside.
class TabsBlock extends ModuleBlock implements CacheableBlock, HasStarterBlocks, RestrictsChildBlocks
{
    public const NAME = 'tabs';

    // Attr given to each tab while it is drawn: its place in the list, from 0.
    public const INDEX = 'verso:index';

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return __('Tabs');
    }

    public function description(): string
    {
        return __('Content in tabs. People pick a tab to see its part.');
    }

    public function starter(): array
    {
        return [
            ['type' => TabBlock::NAME, 'attrs' => ['title' => __('First tab')], 'children' => [['type' => 'paragraph', 'content' => __('Write the first part here.')]]],
            ['type' => TabBlock::NAME, 'attrs' => ['title' => __('Second tab')], 'children' => [['type' => 'paragraph', 'content' => __('Write the second part here.')]]],
        ];
    }

    public function childTypes(): array
    {
        return [TabBlock::NAME];
    }

    public function acceptsChild(string $type): bool
    {
        return $type === TabBlock::NAME;
    }

    public function fields(): array
    {
        return [
            new BlockField('label', __('Name for screen readers'), BlockFieldType::Text, '', help: __('Like "Opening hours". Says what the tabs are about.'), max: 120),
        ];
    }

    protected function html(BlockData $block, BlockRenderer $renderer): string
    {
        $tabs = $this->childrenOf($block, TabBlock::NAME);

        if ($tabs === []) {
            return '';
        }

        $buttons = '';
        $panels = '';

        foreach ($tabs as $index => $tab) {
            $selected = $index === 0;
            $buttons .= '<button type="button" role="tab" id="'.$this->domId($tab, 'tab').'" aria-controls="'.$this->domId($tab, 'panel').'"'
                .' aria-selected="'.($selected ? 'true' : 'false').'" tabindex="'.($selected ? '0' : '-1').'">'
                .e($this->text($tab, 'title', __('Tab :number', ['number' => $index + 1]))).'</button>';
            $tab = $this->withAttr($tab, self::INDEX, $index);
            $panels .= $this->child($renderer, $tab);
        }

        $label = $this->text($block, 'label');

        return '<div class="verso-tabs" data-verso-tabs>'
            .'<div class="verso-tabs__list" role="tablist"'.($label !== '' ? ' aria-label="'.e($label).'"' : '').'>'.$buttons.'</div>'
            .$panels.'</div>';
    }
}
