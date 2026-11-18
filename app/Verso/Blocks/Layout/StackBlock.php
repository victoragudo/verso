<?php

namespace App\Verso\Blocks\Layout;

use App\Verso\Blocks\Support\ModuleBlock;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Contracts\CacheableBlock;
use App\Verso\Plugins\Contracts\HasChildBlocks;
use App\Verso\Plugins\Enums\BlockFieldType;

// Blocks one after another with even space, up and down or side by side.
class StackBlock extends ModuleBlock implements CacheableBlock, HasChildBlocks
{
    public const NAME = 'stack';

    public const DIRECTIONS = ['vertical', 'horizontal'];

    public const SPACES = ['none', 'small', 'medium', 'large'];

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return __('Stack');
    }

    public function description(): string
    {
        return __('Blocks one after another with the same space between them.');
    }

    public function category(): string
    {
        return 'layout';
    }

    public function acceptsChild(string $type): bool
    {
        return $type !== 'column';
    }

    public function fields(): array
    {
        return [
            new BlockField('direction', __('Direction'), BlockFieldType::Select, 'vertical', ['vertical' => __('Top to bottom'), 'horizontal' => __('Side by side')]),
            new BlockField('space', __('Space between'), BlockFieldType::Select, 'medium', ['none' => __('None'), 'small' => __('Small'), 'medium' => __('Medium'), 'large' => __('Large')]),
            new BlockField('wrap', __('Wrap to a new line when there is no room'), BlockFieldType::Toggle, true),
        ];
    }

    protected function html(BlockData $block, BlockRenderer $renderer): string
    {
        $classes = 'verso-stack verso-stack--'.$this->choice($block, 'direction', self::DIRECTIONS, 'vertical')
            .' verso-stack--space-'.$this->choice($block, 'space', self::SPACES, 'medium')
            .($block->bool('wrap', true) ? '' : ' verso-stack--nowrap');

        return '<div class="'.$classes.'">'.$renderer->renderChildren($block).'</div>';
    }

    public function assets(): array
    {
        return ['layout'];
    }
}
