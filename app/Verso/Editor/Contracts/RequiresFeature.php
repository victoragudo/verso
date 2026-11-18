<?php

namespace App\Verso\Editor\Contracts;

// A block that only people with an editor feature may add, like the HTML element block.
interface RequiresFeature
{
    // Key of the feature in the editor feature registry, like "element_block".
    public function feature(): string;
}
