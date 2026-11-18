<?php

namespace App\Verso\Library;

use Illuminate\Foundation\Events\Dispatchable;

// A library item was saved or deleted. Pages that use a synced block must be drawn again.
class LibraryChanged
{
    use Dispatchable;

    public function __construct(public int $itemId, public string $kind) {}
}
