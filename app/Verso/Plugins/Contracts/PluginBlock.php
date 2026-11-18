<?php

namespace App\Verso\Plugins\Contracts;

use App\Verso\Content\Models\Entry;

// A block type added by a plugin.
interface PluginBlock
{
    // The block type stored in entries.blocks, like "reading-time".
    public function name(): string;

    // A short name shown in the editor.
    public function label(): string;

    /**
     * Turn one block into safe HTML. The entry is given when the block is part of one.
     *
     * @param  array<mixed>  $block
     */
    public function render(array $block, ?Entry $entry = null): string;
}
