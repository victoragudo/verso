<?php

namespace App\Verso\Editor\Builder;

// Decides if a block may be placed inside another block.
interface DropRule
{
    // True to allow, false to refuse, null to let the next rule or the parent block decide.
    public function allows(string $parent, string $child): ?bool;
}
