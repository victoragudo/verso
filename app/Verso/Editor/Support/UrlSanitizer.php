<?php

namespace App\Verso\Editor\Support;

// Checks URLs from block content and keeps only safe schemes like http, https and mailto.
class UrlSanitizer
{
    /** @var list<string> */
    protected array $schemes;

    /**
     * @param  list<string>  $schemes
     */
    public function __construct(array $schemes)
    {
        $this->schemes = array_map(strtolower(...), $schemes);
    }

    // Returns a clean URL, or null when it is empty or uses a scheme that is not allowed.
    public function clean(mixed $url): ?string
    {
        if (! is_string($url)) {
            return null;
        }

        $url = (string) preg_replace('/[\x00-\x1F\x7F]/', '', trim($url));

        if ($url === '') {
            return null;
        }

        // Browsers ignore spaces and control characters inside the scheme, so we do the same before checking it.
        $compact = (string) preg_replace('/[\x00-\x20\x7F]+/', '', $url);

        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $compact, $match) === 1) {
            return in_array(strtolower($match[1]), $this->schemes, true) ? $url : null;
        }

        return $url;
    }

    // True when the URL is safe to print.
    public function isSafe(mixed $url): bool
    {
        return $this->clean($url) !== null;
    }

    // Like clean(), but only accepts absolute http and https URLs.
    public function cleanHttp(mixed $url): ?string
    {
        $url = $this->clean($url);

        if ($url === null || preg_match('#^https?://[^\s/?\#]+#i', $url) !== 1) {
            return null;
        }

        return $url;
    }
}
