<?php

namespace App\Verso\Plugins\Contracts;

// A plugin block that holds other blocks as children, like a box around content.
interface HasChildBlocks
{
    // True when a block of this type can be placed inside this block.
    public function acceptsChild(string $type): bool;
}
