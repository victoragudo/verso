<?php

namespace App\Verso\Plugins\Filters;

use App\Verso\Plugins\Contracts\Filter;
use App\Verso\Plugins\FilterRegistry;
use App\Verso\Site\Sitemap\SitemapUrl;

// Changes the rows of one sitemap file, like "post", "page-2" or "taxonomy-category".
// Callbacks: fn (array $urls, string $sitemap, int $page): array. Each row is a SitemapUrl.
final class SitemapEntriesFilter implements Filter
{
    /**
     * @param  list<SitemapUrl>  $urls
     * @return list<SitemapUrl>
     */
    public static function apply(array $urls, string $sitemap, int $page): array
    {
        $urls = (array) app(FilterRegistry::class)->apply(self::class, $urls, $sitemap, $page);

        return array_values(array_filter($urls, fn (mixed $url): bool => $url instanceof SitemapUrl));
    }
}
