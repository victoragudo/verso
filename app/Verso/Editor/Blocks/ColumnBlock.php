<?php

namespace App\Verso\Editor\Blocks;

use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;

// One column inside a "columns" block. It can hold any block except other columns.
class ColumnBlock extends BaseBlock
{
    public function name(): string
    {
        return 'column';
    }

    public function rules(): array
    {
        return [
            'width' => ['nullable', 'integer', 'between:1,100'],
        ];
    }

    public function acceptsChild(string $type): bool
    {
        return ! in_array($type, ['columns', 'column'], true);
    }

    public function render(BlockData $block, BlockRenderer $renderer): string
    {
        $width = $block->int('width');
        $style = $width !== null && $width >= 1 && $width <= 100 ? $this->attribute('style', "flex-basis: {$width}%") : '';

        return '<div class="verso-column"'.$style.'>'.$renderer->renderChildren($block).'</div>';
    }
}
