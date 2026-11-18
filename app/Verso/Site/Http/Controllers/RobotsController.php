<?php

namespace App\Verso\Site\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Verso\Site\Seo\SeoSettings;
use App\Verso\Site\Sitemap\SitemapSettings;
use Illuminate\Http\Response;

// Serves /robots.txt from the seo.robots_txt setting, with a link to the sitemap when it is on.
class RobotsController extends Controller
{
    // Sent instead of the setting when the site asks search engines to stay away.
    public const BLOCK_ALL = "User-agent: *\nDisallow: /";

    public function __invoke(SeoSettings $seo, SitemapSettings $sitemap): Response
    {
        $rules = $seo->discourage() ? self::BLOCK_ALL : trim($seo->robotsTxt());

        // No sitemap line when the sitemap is off or the site hides from search engines.
        $body = $sitemap->enabled() && ! $seo->discourage()
            ? $rules."\n\nSitemap: ".url('/sitemap.xml')."\n"
            : $rules."\n";

        return response(ltrim($body), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
