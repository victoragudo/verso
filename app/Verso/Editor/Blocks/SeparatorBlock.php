<?php

namespace App\Verso\Editor\Blocks;

use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;

// A horizontal line between two parts of the content.
class SeparatorBlock extends BaseBlock
{
    public function name(): string
    {
        return 'separator';
    }

    public function render(BlockData $block, BlockRenderer $renderer): string
    {
        return '<hr>';
    }
}
