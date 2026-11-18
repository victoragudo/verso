<?php

namespace App\Verso\Blocks\Interactive;

use App\Verso\Blocks\Support\ModuleBlock;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Contracts\CacheableBlock;
use App\Verso\Plugins\Enums\BlockFieldType;

// A labelled bar that shows how far something is, like "Funding: 70%". A native <progress>, no JavaScript.
class ProgressBarBlock extends ModuleBlock implements CacheableBlock
{
    public const NAME = 'progress-bar';

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return __('Progress bar');
    }

    public function description(): string
    {
        return __('A bar that shows how far something is.');
    }

    public function fields(): array
    {
        return [
            new BlockField('label', __('Label'), BlockFieldType::Text, '', required: true, max: 120),
            new BlockField('value', __('Value'), BlockFieldType::Number, 50, required: true, min: 0, max: 1000000),
            new BlockField('max', __('Maximum'), BlockFieldType::Number, 100, min: 1, max: 1000000),
            new BlockField('showValue', __('Show the percent'), BlockFieldType::Toggle, true),
        ];
    }

    protected function html(BlockData $block, BlockRenderer $renderer): string
    {
        $max = $this->number($block, 'max', 100, 1, 1000000);
        $value = $this->number($block, 'value', 0, 0, $max);
        $id = $this->domId($block, 'progress');
        $percent = (int) round($value / $max * 100);

        return '<div class="verso-progress">'
            .'<label class="verso-progress__label" for="'.$id.'"><span>'.e($this->text($block, 'label')).'</span>'
            .($block->bool('showValue', true) ? '<span class="verso-progress__value">'.e(__(':percent%', ['percent' => $percent])).'</span>' : '')
            .'</label><progress class="verso-progress__bar" id="'.$id.'" max="'.e((string) $max).'" value="'.e((string) $value).'"></progress></div>';
    }
}
