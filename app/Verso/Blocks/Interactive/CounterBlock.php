<?php

namespace App\Verso\Blocks\Interactive;

use App\Verso\Blocks\Support\ModuleBlock;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Contracts\CacheableBlock;
use App\Verso\Plugins\Enums\BlockFieldType;

// A big number that counts up when it scrolls into view, like "1,200 happy guests".
// The final number is in the page, so it reads right without JavaScript and for screen readers.
class CounterBlock extends ModuleBlock implements CacheableBlock
{
    public const NAME = 'counter';

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return __('Counter');
    }

    public function description(): string
    {
        return __('A number that counts up when people scroll to it.');
    }

    public function fields(): array
    {
        return [
            new BlockField('number', __('Number'), BlockFieldType::Number, 100, required: true, min: -1000000000, max: 1000000000),
            new BlockField('decimals', __('Decimals'), BlockFieldType::Number, 0, min: 0, max: 3),
            new BlockField('prefix', __('Before the number'), BlockFieldType::Text, '', help: __('Like "€" or "+".'), max: 10),
            new BlockField('suffix', __('After the number'), BlockFieldType::Text, '', help: __('Like "%" or "k".'), max: 10),
            new BlockField('label', __('Label'), BlockFieldType::Text, '', help: __('Like "happy guests".'), max: 120),
            new BlockField('duration', __('Counting time in seconds'), BlockFieldType::Number, 2, min: 0, max: 10),
        ];
    }

    protected function html(BlockData $block, BlockRenderer $renderer): string
    {
        $number = $this->number($block, 'number', 0, -1000000000, 1000000000);
        $decimals = (int) $this->number($block, 'decimals', 0, 0, 3);
        $final = number_format($number, $decimals, '.', '');
        $label = $this->text($block, 'label');

        return '<div class="verso-counter">'
            .'<p class="verso-counter__value" data-verso-counter data-to="'.e($final).'" data-decimals="'.$decimals.'"'
            .' data-duration="'.(int) ($this->number($block, 'duration', 2, 0, 10) * 1000).'">'
            .'<span class="verso-counter__prefix">'.e($this->text($block, 'prefix')).'</span>'
            .'<span class="verso-counter__number">'.e($final).'</span>'
            .'<span class="verso-counter__suffix">'.e($this->text($block, 'suffix')).'</span></p>'
            .($label !== '' ? '<p class="verso-counter__label">'.e($label).'</p>' : '')
            .'</div>';
    }
}
