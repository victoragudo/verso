<?php

namespace App\Verso\Plugins\Contracts;

// A plugin block with children that only takes some block types inside, like tabs that only take tabs.
// The editor reads the list to offer only those blocks.
interface RestrictsChildBlocks extends HasChildBlocks
{
    /** @return list<string> */
    public function childTypes(): array;
}
