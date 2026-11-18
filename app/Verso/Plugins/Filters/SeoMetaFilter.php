<?php

namespace App\Verso\Plugins\Filters;

use App\Verso\Content\Models\Entry;
use App\Verso\Plugins\Contracts\Filter;
use App\Verso\Plugins\FilterRegistry;
use App\Verso\Site\Seo\SeoPage;

// Changes the SEO data of a public page: title, description, robots, Open Graph, Twitter and schema.
// Callbacks: fn (SeoPage $seo, ?Entry $entry): SeoPage. Use the with...() methods of SeoPage.
final class SeoMetaFilter implements Filter
{
    public static function apply(SeoPage $seo, ?Entry $entry): SeoPage
    {
        /** @var SeoPage */
        return app(FilterRegistry::class)->apply(self::class, $seo, $entry);
    }
}
