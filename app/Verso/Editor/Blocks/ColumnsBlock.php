<?php

namespace App\Verso\Editor\Blocks;

use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;

// A row of columns. Its children must be "column" blocks.
class ColumnsBlock extends BaseBlock
{
    public function name(): string
    {
        return 'columns';
    }

    public function acceptsChild(string $type): bool
    {
        return $type === 'column';
    }

    public function render(BlockData $block, BlockRenderer $renderer): string
    {
        $columns = $renderer->renderChildren($block);

        return $columns === '' ? '' : '<div class="verso-columns">'.$columns.'</div>';
    }
}
