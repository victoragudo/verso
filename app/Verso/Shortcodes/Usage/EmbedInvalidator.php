<?php

namespace App\Verso\Shortcodes\Usage;

use App\Verso\Theme\Cache\PageCache;

// Clears the cached pages of the entries that embed a changed item, like a form or a product.
class EmbedInvalidator
{
    public function __construct(
        protected UsageIndex $usage,
        protected PageCache $pages,
        protected bool $targeted = true,
    ) {}

    // Call when an item changes. Pass old and new keys when a slug changes.
    public function changed(string $name, string ...$keys): void
    {
        if (! $this->targeted) {
            $this->pages->flush();

            return;
        }

        $keys = array_values(array_unique(array_filter($keys, fn (string $key): bool => $key !== '')));

        foreach ($this->usage->entriesUsing($name, $keys) as $entry) {
            $this->pages->forget($entry->url());
        }
    }
}
