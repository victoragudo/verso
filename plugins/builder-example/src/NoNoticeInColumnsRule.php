<?php

namespace Verso\Plugins\BuilderExample;

use App\Verso\Editor\Builder\DropRule;

// Notices are wide boxes, so this rule keeps them out of narrow columns.
class NoNoticeInColumnsRule implements DropRule
{
    public function allows(string $parent, string $child): ?bool
    {
        return $parent === 'column' && $child === BuilderExampleServiceProvider::BLOCK ? false : null;
    }
}
