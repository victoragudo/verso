<?php

namespace App\Verso\Blocks\Layout;

use App\Verso\Blocks\Support\ModuleBlock;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Contracts\CacheableBlock;
use App\Verso\Plugins\Contracts\HasChildBlocks;
use App\Verso\Plugins\Enums\BlockFieldType;

// A grid of blocks. By default it fits as many columns as there is room for; the Styles panel sets fixed columns per screen size.
class GridBlock extends ModuleBlock implements CacheableBlock, HasChildBlocks
{
    public const NAME = 'grid';

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return __('Grid');
    }

    public function description(): string
    {
        return __('Blocks in rows and columns that wrap on small screens.');
    }

    public function category(): string
    {
        return 'layout';
    }

    public function acceptsChild(string $type): bool
    {
        return $type !== 'column';
    }

    // Smallest column widths people can pick, in rem.
    public const MIN_WIDTHS = [8, 10, 12, 14, 16, 18, 20, 24, 28, 32];

    // Space between cells, in rem. Other sizes are set with Layout, Gap in the Styles panel.
    public const GAPS = ['0', '0.5', '1', '1.5', '2', '3'];

    public function fields(): array
    {
        $widths = [];

        foreach (self::MIN_WIDTHS as $width) {
            $widths[(string) $width] = __(':size rem', ['size' => $width]);
        }

        return [
            new BlockField('minWidth', __('Smallest column width'), BlockFieldType::Select, '16', $widths, help: __('Columns never get narrower than this, so fewer columns fit on small screens. For fixed columns per screen size, use Layout in the Styles panel.')),
            new BlockField('gap', __('Space between'), BlockFieldType::Select, '1.5', array_combine(self::GAPS, [__('None'), __('Very small'), __('Small'), __('Medium'), __('Large'), __('Very large')])),
        ];
    }

    protected function html(BlockData $block, BlockRenderer $renderer): string
    {
        $min = $this->choice($block, 'minWidth', array_map('strval', self::MIN_WIDTHS), '16');
        $gap = $this->choice($block, 'gap', self::GAPS, '1.5');

        return '<div class="verso-grid verso-grid--min-'.$min.' verso-grid--gap-'.str_replace('.', '_', $gap).'">'.$renderer->renderChildren($block).'</div>';
    }

    public function assets(): array
    {
        return ['layout'];
    }
}
