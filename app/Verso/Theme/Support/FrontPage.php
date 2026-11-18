<?php

namespace App\Verso\Theme\Support;

use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;

// Reads the "reading.front_page" setting: the page shown on "/" instead of the latest posts.
class FrontPage
{
    public function __construct(protected Settings $settings) {}

    // The front page entry, or null when none is set or it is not published.
    public function entry(): ?Entry
    {
        $id = $this->settings->get('reading.front_page');

        if (! is_numeric($id) || (int) $id <= 0) {
            return null;
        }

        return Entry::query()->published()->find((int) $id);
    }
}
