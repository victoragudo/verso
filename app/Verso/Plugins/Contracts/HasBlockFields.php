<?php

namespace App\Verso\Plugins\Contracts;

use App\Verso\Plugins\Blocks\BlockField;

// A plugin block that describes its attrs, so the editor can show fields for them.
interface HasBlockFields
{
    // One short line shown under the label in the block menus.
    public function description(): string;

    /**
     * The attrs the block uses, in the order the editor shows them.
     *
     * @return list<BlockField>
     */
    public function fields(): array;
}
