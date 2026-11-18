<?php

namespace App\Verso\Fields\Theme;

use App\Verso\Content\Models\Entry;

// Remembers the entry of the page being shown, so field('name') works without passing it.
class CurrentEntry
{
    protected ?Entry $entry = null;

    public function set(?Entry $entry): void
    {
        $this->entry = $entry;
    }

    public function get(): ?Entry
    {
        return $this->entry;
    }
}
