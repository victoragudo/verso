<?php

namespace App\Verso\Core\Settings;

use App\Verso\Core\Models\Setting;
use Illuminate\Contracts\Cache\Repository as Cache;

// Reads and writes site settings. Values are kept in memory and in the cache store.
class Settings
{
    /** @var array<string, mixed>|null */
    protected ?array $loaded = null;

    /**
     * @param  array<string, mixed>  $defaults
     */
    public function __construct(
        protected Cache $cache,
        protected string $cacheKey,
        protected ?int $cacheTtl = null,
        protected array $defaults = [],
    ) {}

    // The stored value, or the given default, or the default from config/verso.php.
    public function get(string $key, mixed $default = null): mixed
    {
        $values = $this->all();

        if (array_key_exists($key, $values)) {
            return $values[$key];
        }

        return $default ?? ($this->defaults[$key] ?? null);
    }

    public function set(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        $this->forgetCache();
    }

    /**
     * Save many settings at once.
     *
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->forgetCache();
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    public function forget(string $key): void
    {
        Setting::query()->where('key', $key)->delete();

        $this->forgetCache();
    }

    /**
     * Delete every setting whose key starts with the prefix, like "theme.harbour." Returns the deleted keys.
     *
     * @return list<string>
     */
    public function forgetPrefix(string $prefix): array
    {
        if ($prefix === '') {
            return [];
        }

        $this->forgetCache();
        $keys = array_values(array_filter(
            array_map(strval(...), array_keys($this->all())),
            fn (string $key): bool => str_starts_with($key, $prefix),
        ));

        if ($keys !== []) {
            Setting::query()->whereIn('key', $keys)->delete();
            $this->forgetCache();
        }

        return $keys;
    }

    /**
     * All stored settings as key => value.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->loaded ??= $this->loadFromCache();
    }

    // Drop the memory and cache copies so the next read hits the database.
    public function forgetCache(): void
    {
        $this->loaded = null;
        $this->cache->forget($this->cacheKey);
    }

    /**
     * @return array<string, mixed>
     */
    protected function loadFromCache(): array
    {
        $load = fn (): array => Setting::query()->pluck('value', 'key')->all();

        /** @var array<string, mixed> $values */
        $values = $this->cacheTtl === null
            ? $this->cache->rememberForever($this->cacheKey, $load)
            : $this->cache->remember($this->cacheKey, $this->cacheTtl, $load);

        return $values;
    }
}
