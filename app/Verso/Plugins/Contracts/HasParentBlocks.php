<?php

namespace App\Verso\Plugins\Contracts;

// A plugin block that may only sit directly inside some block types, like a tab inside tabs.
interface HasParentBlocks
{
    /** @return list<string> */
    public function parents(): array;
}
