<?php

namespace App\Verso\Editor\Builder;

use App\Verso\Editor\Contracts\Block;

/**
 * Keeps the drop rules. The last rule added that gives an answer wins; with no answer the parent block decides.
 *
 * @extends Registry<DropRule>
 */
class DropRuleRegistry extends Registry
{
    public function register(string $key, DropRule $rule): void
    {
        $this->put($key, $rule);
    }

    // True when a block of type $child may be placed inside $parent.
    public function accepts(Block $parent, string $child): bool
    {
        foreach (array_reverse($this->items) as $rule) {
            $answer = $rule->allows($parent->name(), $child);

            if ($answer !== null) {
                return $answer;
            }
        }

        return $parent->acceptsChild($child);
    }
}
