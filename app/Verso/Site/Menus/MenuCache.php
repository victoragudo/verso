<?php

namespace App\Verso\Site\Menus;

use Closure;
use Illuminate\Contracts\Cache\Repository as Cache;

// Keeps the link trees of menu locations in the cache. flush() drops them all by moving to a new version.
class MenuCache
{
    // Shape of the cached link trees. Raise it when a link gets new keys, so trees cached by an older version are not read.
    public const FORMAT = 2;

    // Version read once per request, so many menus on one page cost one cache read for it.
    protected ?int $version = null;

    public function __construct(
        protected Cache $cache,
        protected string $prefix,
        protected int $ttl,
    ) {}

    /**
     * @param  Closure(): array<string, mixed>  $build
     * @return array<string, mixed>
     */
    public function remember(string $location, string $root, Closure $build): array
    {
        if ($this->ttl < 1) {
            return $build();
        }

        $key = $this->key($location, $root);
        $cached = $this->cache->get($key);

        if (is_array($cached)) {
            return $cached;
        }

        $value = $build();
        $this->cache->put($key, $value, $this->ttl);

        return $value;
    }

    public function flush(): void
    {
        $this->version = $this->version() + 1;
        $this->cache->forever($this->versionKey(), $this->version);
    }

    // The site root is part of the key because links are full URLs.
    public function key(string $location, string $root): string
    {
        return $this->prefix.'.f'.self::FORMAT.'.v'.$this->version().'.'.$location.'.'.sha1($root);
    }

    protected function version(): int
    {
        return $this->version ??= (int) $this->cache->get($this->versionKey(), 1);
    }

    protected function versionKey(): string
    {
        return $this->prefix.'.version';
    }
}
