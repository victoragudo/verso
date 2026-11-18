<?php

namespace App\Verso\Site\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Verso\Site\Sitemap\SitemapBuilder;
use App\Verso\Site\Sitemap\SitemapCache;
use App\Verso\Site\Sitemap\SitemapSettings;
use Illuminate\Http\Response;

// Serves /sitemap.xml and the sitemap file of each content type and taxonomy, from the cache.
class SitemapController extends Controller
{
    public const CONTENT_TYPE = 'application/xml; charset=UTF-8';

    public function __construct(
        protected SitemapBuilder $builder,
        protected SitemapCache $cache,
        protected SitemapSettings $settings,
    ) {}

    public function index(): Response
    {
        abort_unless($this->settings->enabled(), 404);

        $xml = (string) $this->cache->remember('index', fn (): string => $this->builder->index());

        return $this->xml($xml);
    }

    public function show(string $sitemap): Response
    {
        abort_unless($this->settings->enabled(), 404);

        $taxonomyFile = $this->builder->taxonomyFromFileName($sitemap);

        if ($taxonomyFile !== null) {
            [$taxonomy, $page] = $this->builder->parseFileName($taxonomyFile, array_keys($this->builder->taxonomies()));

            $xml = $this->cache->remember("taxonomy.{$taxonomy}.{$page}", fn (): ?string => $this->builder->forTaxonomy($taxonomy, $page));
        } else {
            [$type, $page] = $this->builder->parseFileName($sitemap);

            $xml = $this->cache->remember("type.{$type}.{$page}", fn (): ?string => $this->builder->forType($type, $page));
        }

        abort_if($xml === null, 404);

        return $this->xml($xml);
    }

    protected function xml(string $xml): Response
    {
        return response($xml, 200, ['Content-Type' => self::CONTENT_TYPE]);
    }
}
