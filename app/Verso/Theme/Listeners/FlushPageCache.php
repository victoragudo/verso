<?php

namespace App\Verso\Theme\Listeners;

use App\Verso\Theme\Cache\PageCache;

// Empties the page cache when content or settings change.
class FlushPageCache
{
    public function __construct(protected PageCache $cache) {}

    public function handle(object $event): void
    {
        $this->cache->flush();
    }
}
