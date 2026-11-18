<?php

namespace App\Verso\Site\Listeners;

use App\Verso\Site\Sitemap\SitemapCache;

// Drops the cached sitemaps when entries or site settings change.
class InvalidateSitemap
{
    public function __construct(protected SitemapCache $cache) {}

    public function handle(object $event): void
    {
        $this->cache->flush();
    }
}
