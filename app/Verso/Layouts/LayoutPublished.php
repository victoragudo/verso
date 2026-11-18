<?php

namespace App\Verso\Layouts;

use Illuminate\Foundation\Events\Dispatchable;

// A layout was published, turned off or deleted. Cached pages must be drawn again.
class LayoutPublished
{
    use Dispatchable;

    public function __construct(public int $layoutId, public LayoutKind $kind) {}
}
