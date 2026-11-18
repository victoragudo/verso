<?php

namespace App\Verso\Theme\AdminBar;

use App\Verso\Content\Models\Entry;

// Remembers the entry shown on the current page, so the admin bar can link to its editor.
class AdminBarContext
{
    protected ?Entry $entry = null;

    public function viewing(Entry $entry): void
    {
        $this->entry = $entry;
    }

    public function entry(): ?Entry
    {
        return $this->entry;
    }
}
