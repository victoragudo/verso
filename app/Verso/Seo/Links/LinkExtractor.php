<?php

namespace App\Verso\Seo\Links;

use App\Verso\Core\Models\Redirect;
use DOMDocument;

// Reads the links of a page and splits them into paths on this site and links to other sites.
class LinkExtractor
{
    // Links that do not lead to a page.
    protected const SKIPPED_SCHEMES = ['mailto:', 'tel:', 'javascript:', 'data:', 'sms:'];

    /**
     * @param  list<string>  $siteHosts  hosts that count as this site, like "harborlane.example.org"
     */
    public function __construct(
        protected array $siteHosts,
        protected int $maxLinks,
    ) {}

    public function extract(string $html): FoundLinks
    {
        $internal = [];
        $external = 0;

        foreach (array_slice($this->hrefs($html), 0, max(1, $this->maxLinks)) as $href) {
            $path = $this->internalPath($href);

            if ($path === false) {
                continue;
            }

            if ($path === null) {
                $external++;

                continue;
            }

            $internal[$path] = true;
        }

        return new FoundLinks(array_keys($internal), $external);
    }

    // The path of a link to this site, null for a link to another site, false for links to skip.
    public function internalPath(string $href): string|false|null
    {
        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $lower = strtolower($href);

        if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, '?')) {
            return false;
        }

        foreach (self::SKIPPED_SCHEMES as $scheme) {
            if (str_starts_with($lower, $scheme)) {
                return false;
            }
        }

        if (str_starts_with($href, '//')) {
            $href = 'https:'.$href;
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $href) === 1) {
            $host = parse_url($href, PHP_URL_HOST);

            if (! is_string($host) || ! in_array(strtolower($host), $this->siteHosts, true)) {
                return preg_match('#^https?://#i', $href) === 1 ? null : false;
            }
        } elseif (! str_starts_with($href, '/')) {
            // Relative links like "menu.pdf" depend on the page address; they are rare in stored content.
            return false;
        }

        return Redirect::normalizePath($href);
    }

    /** @return list<string> */
    protected function hrefs(string $html): array
    {
        if (trim($html) === '' || stripos($html, '<a') === false) {
            return [];
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_NONET | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $hrefs = [];
        foreach ($document->getElementsByTagName('a') as $anchor) {
            if ($anchor->hasAttribute('href')) {
                $hrefs[] = $anchor->getAttribute('href');
            }
        }

        return $hrefs;
    }
}
