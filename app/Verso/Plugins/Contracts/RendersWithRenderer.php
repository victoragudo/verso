<?php

namespace App\Verso\Plugins\Contracts;

use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;

// A plugin block that draws itself with the block renderer, so it can draw its children with their styles.
// The current entry and page are in App\Verso\Editor\Support\RenderContext.
interface RendersWithRenderer
{
    public function renderWith(BlockData $block, BlockRenderer $renderer): string;
}
