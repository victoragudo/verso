<?php

namespace App\Verso\Editor\Builder;

use App\Verso\Editor\BlockRegistry;
use App\Verso\Plugins\Blocks\PluginBlockAdapter;
use Closure;

// Blocks that name their parents (a tab only inside tabs) may only go there. Other blocks are left to the next rule.
class ParentDropRule implements DropRule
{
    /** @param Closure(): BlockRegistry $blocks */
    public function __construct(protected Closure $blocks) {}

    public function allows(string $parent, string $child): ?bool
    {
        $type = ($this->blocks)()->find($child);
        $parents = $type instanceof PluginBlockAdapter ? $type->parents() : null;

        return $parents === null ? null : in_array($parent, $parents, true);
    }
}
