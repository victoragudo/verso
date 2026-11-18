<?php

namespace Verso\Plugins\EventCalendar;

use App\Verso\Content\Models\Entry;
use App\Verso\Plugins\Contracts\PluginBlock;

// Test block that prints the venue name.
class VenueBlock implements PluginBlock
{
    public function name(): string
    {
        return 'venue';
    }

    public function label(): string
    {
        return 'Venue';
    }

    public function render(array $block, ?Entry $entry = null): string
    {
        return '<p class="venue">'.e((string) ($block['attrs']['name'] ?? '')).'</p>';
    }
}
