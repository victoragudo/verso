<?php

namespace App\Verso\Editor\Blocks;

use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;

// Raw HTML printed as it is. Only trusted users (see verso.editor.html_capability) can save it.
class HtmlBlock extends BaseBlock
{
    public function name(): string
    {
        return 'html';
    }

    public function capability(): ?string
    {
        $capability = config('verso.editor.html_capability');

        return is_string($capability) && $capability !== '' ? $capability : null;
    }

    public function render(BlockData $block, BlockRenderer $renderer): string
    {
        return $block->content;
    }
}
