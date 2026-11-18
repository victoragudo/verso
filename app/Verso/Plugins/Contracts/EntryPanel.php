<?php

namespace App\Verso\Plugins\Contracts;

use App\Verso\Plugins\Blocks\BlockField;

// A small box in the entry editor side panel. Its values are saved in entries.meta under key().
interface EntryPanel
{
    // The meta key that holds the panel values, like "membership".
    public function key(): string;

    public function title(): string;

    // One short line shown under the title.
    public function description(): string;

    // True when the panel shows for entries of this content type.
    public function appliesTo(string $contentType): bool;

    /**
     * The values the panel edits, in the order the editor shows them.
     *
     * @return list<BlockField>
     */
    public function fields(): array;
}
