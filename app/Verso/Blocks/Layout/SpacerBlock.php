<?php

namespace App\Verso\Blocks\Layout;

use App\Verso\Blocks\Support\ModuleBlock;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Contracts\CacheableBlock;
use App\Verso\Plugins\Enums\BlockFieldType;

// Empty space between blocks. Its height can change per screen size in the Styles panel (Size, Height).
class SpacerBlock extends ModuleBlock implements CacheableBlock
{
    public const NAME = 'spacer';

    public const SIZES = ['small', 'medium', 'large', 'x-large'];

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return __('Spacer');
    }

    public function description(): string
    {
        return __('Empty space between two blocks.');
    }

    public function category(): string
    {
        return 'layout';
    }

    public function fields(): array
    {
        return [
            new BlockField('size', __('Height'), BlockFieldType::Select, 'medium', ['small' => __('Small'), 'medium' => __('Medium'), 'large' => __('Large'), 'x-large' => __('Extra large')], help: __('For an exact height, use Size in the Styles panel.')),
        ];
    }

    protected function html(BlockData $block, BlockRenderer $renderer): string
    {
        return '<div class="verso-spacer verso-spacer--'.$this->choice($block, 'size', self::SIZES, 'medium').'" aria-hidden="true"></div>';
    }

    public function assets(): array
    {
        return ['layout'];
    }
}
