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

// A slider of slides with scroll snap: swipe, scroll or use the buttons. Without JavaScript it is a row people can scroll.
// Autoplay always has a pause button and never runs for people who ask for less motion.
class SliderBlock extends ModuleBlock implements CacheableBlock, HasStarterBlocks, RestrictsChildBlocks
{
    public const NAME = 'slider';

    // Attrs given to each slide while it is drawn: its number and the count.
    public const POSITION = 'verso:position';

    public const COUNT = 'verso:count';

    public const PER_VIEW = ['1', '2', '3', '4'];

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return __('Slider');
    }

    public function description(): string
    {
        return __('Slides that people swipe or move with buttons.');
    }

    public function starter(): array
    {
        return [
            ['type' => SlideBlock::NAME, 'children' => [['type' => 'heading', 'attrs' => ['level' => 3], 'content' => __('First slide')], ['type' => 'paragraph', 'content' => __('Add any blocks to a slide.')]]],
            ['type' => SlideBlock::NAME, 'children' => [['type' => 'heading', 'attrs' => ['level' => 3], 'content' => __('Second slide')], ['type' => 'paragraph', 'content' => __('Add any blocks to a slide.')]]],
        ];
    }

    public function childTypes(): array
    {
        return [SlideBlock::NAME];
    }

    public function acceptsChild(string $type): bool
    {
        return $type === SlideBlock::NAME;
    }

    public function fields(): array
    {
        return [
            new BlockField('label', __('Name for screen readers'), BlockFieldType::Text, '', help: __('Like "Customer stories".'), max: 120),
            new BlockField('perView', __('Slides in view on big screens'), BlockFieldType::Select, '1', ['1' => '1', '2' => '2', '3' => '3', '4' => '4']),
            new BlockField('autoplay', __('Move to the next slide by itself'), BlockFieldType::Toggle, false, help: __('A pause button is always shown. It never moves for people who ask for less motion.')),
            new BlockField('interval', __('Seconds per slide'), BlockFieldType::Number, 6, min: 3, max: 30),
            new BlockField('dots', __('Show a button for each slide'), BlockFieldType::Toggle, true),
        ];
    }

    protected function html(BlockData $block, BlockRenderer $renderer): string
    {
        $slides = $this->childrenOf($block, SlideBlock::NAME);

        if ($slides === []) {
            return '';
        }

        $items = '';

        foreach ($slides as $index => $slide) {
            $slide = $this->withAttr($slide, self::POSITION, $index + 1);
            $slide = $this->withAttr($slide, self::COUNT, count($slides));
            $items .= $this->child($renderer, $slide);
        }

        $label = $this->text($block, 'label', __('Slides'));
        $autoplay = $block->bool('autoplay');
        $controls = '<div class="verso-slider__controls">'
            .'<button type="button" class="verso-slider__button" data-direction="previous">'.$this->icon('chevron-right', '', 'verso-icon verso-slider__previous').'<span class="verso-visually-hidden">'.e(__('Previous slide')).'</span></button>'
            .'<button type="button" class="verso-slider__button" data-direction="next">'.$this->icon('chevron-right').'<span class="verso-visually-hidden">'.e(__('Next slide')).'</span></button>'
            .($autoplay ? '<button type="button" class="verso-slider__button" data-verso-pause aria-pressed="false" data-pause-label="'.e(__('Pause slides')).'" data-play-label="'.e(__('Play slides')).'">'.e(__('Pause slides')).'</button>' : '')
            .'</div>';

        return '<section class="verso-slider verso-slider--per-'.$this->choice($block, 'perView', self::PER_VIEW, '1').'" aria-roledescription="'.e(__('carousel')).'" aria-label="'.e($label).'" data-verso-slider'
            .($autoplay ? ' data-autoplay="'.(int) $this->number($block, 'interval', 6, 3, 30).'"' : '').'>'
            .$controls
            .'<div class="verso-slider__track" tabindex="0" role="group" aria-label="'.e($label).'">'.$items.'</div>'
            .'<p class="verso-visually-hidden" role="status" aria-live="polite" data-slide-status data-template="'.e(__('Slide :number of :count')).'"></p>'
            .($block->bool('dots', true) ? '<div class="verso-slider__dots" role="group" aria-label="'.e(__('Choose a slide')).'"></div>' : '')
            .'</section>';
    }
}
