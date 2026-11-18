<?php

namespace App\Verso\Plugins;

use App\Verso\Content\Models\Entry;
use App\Verso\Editor\Styles\StylePresets;
use Closure;
use Illuminate\Contracts\Cache\Repository as Cache;

// Keeps the HTML of core blocks per entry. The key holds a hash of the blocks, so an edit gives a new key.
// flush() drops everything by moving to a new version, for changes the blocks do not show (media, plugins, settings).
class BlockOutputCache
{
    // Version read once per request, so a list of entries costs one read for it.
    protected ?int $version = null;

    public function __construct(
        protected ?Cache $cache,
        protected string $prefix = 'verso.blocks',
        protected int $ttl = 86400,
    ) {}

    public function enabled(): bool
    {
        return $this->cache !== null && $this->ttl > 0;
    }

    /**
     * @param  Closure(): string  $render
     */
    public function remember(Entry $entry, Closure $render): string
    {
        if (! $this->enabled() || ! $entry->exists) {
            return $render();
        }

        $key = $this->key($entry);
        $cached = $this->cache?->get($key);

        if (is_string($cached)) {
            return $cached;
        }

        $html = $render();
        $this->cache?->put($key, $html, $this->ttl);

        return $html;
    }

    public function flush(): void
    {
        if ($this->cache === null) {
            return;
        }

        $this->version = $this->version() + 1;
        $this->cache->forever($this->versionKey(), $this->version);
    }

    // Shape of the cached HTML. It changes when the HTML gains something new, like block styles, so old copies are not used.
    public const int FORMAT = 3;

    // Image URLs are full URLs, so the site root is part of the key.
    public function key(Entry $entry): string
    {
        $blocks = json_encode($entry->blocks ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';

        // The styles of the presets a page uses are part of its HTML, so a changed preset gives a new key.
        $presets = app(StylePresets::class)->fingerprint((array) ($entry->blocks ?? []));

        return $this->prefix.'.f'.self::FORMAT.'.v'.$this->version().'.'.$entry->id.'.'.hash('xxh128', $blocks.'|'.$presets.'|'.url('/'));
    }

    protected function version(): int
    {
        return $this->version ??= (int) ($this->cache?->get($this->versionKey(), 1) ?? 1);
    }

    protected function versionKey(): string
    {
        return $this->prefix.'.version';
    }
}
