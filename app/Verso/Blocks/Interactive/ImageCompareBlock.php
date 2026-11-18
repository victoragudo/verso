<?php

namespace App\Verso\Blocks\Interactive;

use App\Verso\Blocks\Support\ModuleBlock;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Contracts\CacheableBlock;
use App\Verso\Plugins\Enums\BlockFieldType;

// Two pictures on top of each other with a slider to compare them, like before and after.
// It uses a real range input, so it works with the keyboard. Without JavaScript both pictures show side by side.
class ImageCompareBlock extends ModuleBlock implements CacheableBlock
{
    public const NAME = 'image-compare';

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return __('Image comparison');
    }

    public function description(): string
    {
        return __('Two pictures with a slider to compare them, like before and after.');
    }

    public function category(): string
    {
        return 'media';
    }

    public function fields(): array
    {
        return [
            new BlockField('before', __('First picture'), BlockFieldType::Media, null, required: true),
            new BlockField('after', __('Second picture'), BlockFieldType::Media, null, required: true),
            new BlockField('beforeLabel', __('First label'), BlockFieldType::Text, '', help: __('Like "Before".'), max: 40),
            new BlockField('afterLabel', __('Second label'), BlockFieldType::Text, '', help: __('Like "After".'), max: 40),
            new BlockField('start', __('Start position (%)'), BlockFieldType::Number, 50, min: 0, max: 100),
        ];
    }

    protected function html(BlockData $block, BlockRenderer $renderer): string
    {
        $before = $this->media($block, 'before');
        $after = $this->media($block, 'after');

        if ($before === null || $after === null) {
            return '';
        }

        $beforeLabel = $this->text($block, 'beforeLabel', __('Before'));
        $afterLabel = $this->text($block, 'afterLabel', __('After'));
        $start = (int) $this->number($block, 'start', 50, 0, 100);

        return '<figure class="verso-compare" data-verso-compare>'
            .'<div class="verso-compare__frame">'
            .'<div class="verso-compare__after">'.$this->img($after, 'verso-compare__img').'<span class="verso-compare__tag verso-compare__tag--end" aria-hidden="true">'.e($afterLabel).'</span></div>'
            .'<div class="verso-compare__before">'.$this->img($before, 'verso-compare__img').'<span class="verso-compare__tag" aria-hidden="true">'.e($beforeLabel).'</span></div>'
            .'<input class="verso-compare__range" type="range" min="0" max="100" value="'.$start.'"'
            .' aria-label="'.e(__('Show more of :first or :second', ['first' => $beforeLabel, 'second' => $afterLabel])).'"'
            .' data-valuetext="'.e(__(':first: {value}%', ['first' => $beforeLabel])).'" hidden>'
            .'</div></figure>';
    }
}
