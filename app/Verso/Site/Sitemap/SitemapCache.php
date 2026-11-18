<?php

namespace App\Verso\Site\Sitemap;

use Closure;
use Illuminate\Contracts\Cache\Repository as Cache;

// Keeps generated sitemaps in the cache. flush() drops them all by moving to a new version.
class SitemapCache
{
    public function __construct(
        protected Cache $cache,
        protected string $prefix,
        protected int $ttl,
    ) {}

    /**
     * @param  Closure(): ?string  $build
     */
    public function remember(string $name, Closure $build): ?string
    {
        $key = $this->key($name);
        $cached = $this->cache->get($key);

        if (is_string($cached)) {
            return $cached;
        }

        $xml = $build();

        if ($xml !== null) {
            $this->cache->put($key, $xml, $this->ttl);
        }

        return $xml;
    }

    // Old keys stay in the store until they expire, but are never read again.
    public function flush(): void
    {
        $this->cache->forever($this->versionKey(), $this->version() + 1);
    }

    public function key(string $name): string
    {
        return $this->prefix.'.v'.$this->version().'.'.$name;
    }

    protected function version(): int
    {
        return (int) $this->cache->get($this->versionKey(), 1);
    }

    protected function versionKey(): string
    {
        return $this->prefix.'.version';
    }
}
