<?php

namespace App\Verso\Plugins\Contracts;

// A plugin block that starts with some children when people add it, like tabs with two tabs.
interface HasStarterBlocks
{
    /**
     * Child blocks without ids: {type, attrs, content, children}. The editor gives them fresh ids.
     *
     * @return list<array<string, mixed>>
     */
    public function starter(): array;
}
