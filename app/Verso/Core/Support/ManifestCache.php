<?php

namespace App\Verso\Core\Support;

use Closure;
use Illuminate\Contracts\Cache\Repository as Cache;

// Keeps data read from manifest files (theme.json, verso.json, composer's installed.json) in the cache.
// The key holds the inode, times and size of each file, so an edited, added, replaced or removed file is read again at once.
class ManifestCache
{
    public function __construct(
        protected ?Cache $cache,
        protected string $prefix = 'verso.manifest',
        protected int $ttl = 604800,
    ) {}

    /**
     * The cached value for these files, or the value built now. Values must be arrays or scalars.
     *
     * @template TValue
     *
     * @param  list<string>  $files
     * @param  Closure(): TValue  $build
     * @return TValue
     */
    public function remember(string $name, array $files, Closure $build): mixed
    {
        $cache = $this->cache;

        if ($cache === null || $this->ttl < 1) {
            return $build();
        }

        $key = $this->prefix.'.'.$name.'.'.$this->fingerprint($files);
        $cached = $cache->get($key);

        if (is_array($cached) && array_key_exists('value', $cached)) {
            return $cached['value'];
        }

        $value = $build();
        $cache->put($key, ['value' => $value], $this->ttl);

        return $value;
    }

    /**
     * A short hash of the path, inode, change times and size of every file. A replaced folder gets new inodes.
     *
     * @param  list<string>  $files
     */
    public function fingerprint(array $files): string
    {
        $parts = [];

        foreach ($files as $file) {
            $stat = is_file($file) ? stat($file) : false;
            $parts[] = $file.':'.($stat === false ? 'missing' : $stat['ino'].':'.$stat['mtime'].':'.$stat['ctime'].':'.$stat['size']);
        }

        return sha1(implode('|', $parts));
    }
}
