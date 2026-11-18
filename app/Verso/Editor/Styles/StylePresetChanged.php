<?php

namespace App\Verso\Editor\Styles;

use Illuminate\Foundation\Events\Dispatchable;

// A style preset was saved, changed or deleted. Pages that use it must be drawn again.
class StylePresetChanged
{
    use Dispatchable;

    public function __construct(public string $key) {}
}
