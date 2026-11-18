<?php

namespace App\Verso\Blocks\Layout;

use App\Verso\Blocks\Support\ModuleBlock;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Contracts\CacheableBlock;
use App\Verso\Plugins\Enums\BlockFieldType;

// A shaped edge between two sections: a wave, a curve, a slope, a zigzag. It takes the text color, so set
// Colors, Text in the Styles panel to the color of the next section.
class ShapeDividerBlock extends ModuleBlock implements CacheableBlock
{
    public const NAME = 'shape-divider';

    // Drawings in a 1200 by 120 box. The shape fills the bottom part.
    public const SHAPES = [
        'wave' => 'M0,60 C150,110 350,10 600,60 C850,110 1050,10 1200,60 L1200,120 L0,120 Z',
        'curve' => 'M0,120 Q600,0 1200,120 Z',
        'slope' => 'M0,120 L1200,0 L1200,120 Z',
        'triangle' => 'M0,120 L600,10 L1200,120 Z',
        'zigzag' => 'M0,120 L0,60 L100,20 L200,60 L300,20 L400,60 L500,20 L600,60 L700,20 L800,60 L900,20 L1000,60 L1100,20 L1200,60 L1200,120 Z',
        'arrow' => 'M0,0 L0,120 L1200,120 L1200,0 L640,0 L600,60 L560,0 Z',
        'clouds' => 'M0,120 L0,80 C60,40 120,40 180,80 C240,30 330,30 390,80 C450,40 540,40 600,80 C660,30 750,30 810,80 C870,40 960,40 1020,80 C1080,30 1170,40 1200,70 L1200,120 Z',
    ];

    public const HEIGHTS = ['small', 'medium', 'large'];

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return __('Shape divider');
    }

    public function description(): string
    {
        return __('A wave, curve or zigzag edge between two sections.');
    }

    public function category(): string
    {
        return 'layout';
    }

    public function fields(): array
    {
        return [
            new BlockField('shape', __('Shape'), BlockFieldType::Select, 'wave', [
                'wave' => __('Wave'), 'curve' => __('Curve'), 'slope' => __('Slope'), 'triangle' => __('Triangle'),
                'zigzag' => __('Zigzag'), 'arrow' => __('Arrow'), 'clouds' => __('Clouds'),
            ]),
            new BlockField('height', __('Height'), BlockFieldType::Select, 'medium', ['small' => __('Small'), 'medium' => __('Medium'), 'large' => __('Large')]),
            new BlockField('flip', __('Flip side to side'), BlockFieldType::Toggle, false),
            new BlockField('upsideDown', __('Upside down, for the top of a section'), BlockFieldType::Toggle, false),
        ];
    }

    protected function html(BlockData $block, BlockRenderer $renderer): string
    {
        $shape = $this->choice($block, 'shape', array_keys(self::SHAPES), 'wave');
        $classes = 'verso-shape verso-shape--'.$this->choice($block, 'height', self::HEIGHTS, 'medium')
            .($block->bool('flip') ? ' verso-shape--flip' : '')
            .($block->bool('upsideDown') ? ' verso-shape--upside-down' : '');

        return '<div class="'.$classes.'" aria-hidden="true"><svg viewBox="0 0 1200 120" preserveAspectRatio="none" focusable="false"><path fill="currentColor" d="'.self::SHAPES[$shape].'"/></svg></div>';
    }

    public function assets(): array
    {
        return ['layout'];
    }
}
