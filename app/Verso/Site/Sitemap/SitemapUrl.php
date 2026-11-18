<?php

namespace App\Verso\Site\Sitemap;

// One <url> row of a sitemap file. $lastModified is an ISO 8601 date or null. $images are full image URLs.
final readonly class SitemapUrl
{
    /** @param  list<string>  $images */
    public function __construct(
        public string $location,
        public ?string $lastModified = null,
        public array $images = [],
    ) {}
}
