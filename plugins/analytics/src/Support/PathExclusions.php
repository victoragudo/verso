<?php

namespace Verso\Plugins\Analytics\Support;

use App\Verso\Plugins\Filters\PrivatePathsFilter;
use Illuminate\Support\Str;

// Decides which page paths are private and must never be counted.
class PathExclusions
{
    // A UUID, like the token of a shop order.
    protected const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /** @var list<string>|null */
    protected ?array $resolved = null;

    /**
     * @param  list<string>  $patterns  paths from config, like "/admin" or "/preview/*"
     * @param  list<string>  $sensitiveKeys  query keys that mark a private link, like "token"
     */
    public function __construct(
        protected array $patterns,
        protected array $sensitiveKeys,
        protected int $tokenMinLength,
    ) {}

    // True when the path (with an optional query string) must not be recorded.
    public function excludes(string $path, string $query = ''): bool
    {
        if ($this->hasSensitiveQuery($query) || $this->hasToken($path)) {
            return true;
        }

        foreach ($this->patterns() as $pattern) {
            if ($this->matches($path, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Config patterns plus the ones plugins add through PrivatePathsFilter.
     *
     * @return list<string>
     */
    public function patterns(): array
    {
        return $this->resolved ??= array_values(array_unique(array_map(
            fn (string $pattern): string => '/'.trim(trim($pattern), '/'),
            PrivatePathsFilter::apply($this->patterns),
        )));
    }

    /**
     * @return list<string>
     */
    public function sensitiveKeys(): array
    {
        return array_values(array_unique(array_map(strtolower(...), $this->sensitiveKeys)));
    }

    // A pattern without "*" matches the path itself and every path under it.
    public function matches(string $path, string $pattern): bool
    {
        $path = strtolower($path);
        $pattern = strtolower($pattern);

        if (str_contains($pattern, '*')) {
            return Str::is($pattern, $path);
        }

        return $pattern !== '/' && ($path === $pattern || str_starts_with($path, $pattern.'/'));
    }

    public function hasSensitiveQuery(string $query): bool
    {
        $query = ltrim($query, '?');

        if ($query === '') {
            return false;
        }

        parse_str($query, $values);
        $keys = array_map(fn (int|string $key): string => strtolower((string) $key), array_keys($values));

        return array_intersect($keys, $this->sensitiveKeys()) !== [];
    }

    // A path part that looks like a secret: a UUID, or a long run of letters and digits with both in it.
    public function hasToken(string $path): bool
    {
        foreach (explode('/', $path) as $segment) {
            if (preg_match(self::UUID_PATTERN, $segment) === 1) {
                return true;
            }

            if (strlen($segment) >= $this->tokenMinLength
                && preg_match('/^[A-Za-z0-9_]+$/', $segment) === 1
                && preg_match('/\d/', $segment) === 1
                && preg_match('/[A-Za-z]/', $segment) === 1) {
                return true;
            }
        }

        return false;
    }
}
