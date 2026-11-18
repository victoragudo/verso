<?php

namespace App\Verso\Theme\Cache;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Request as BaseRequest;
use Symfony\Component\HttpFoundation\Response;

// Full page cache for guests. Flushing bumps a version number, so old pages are never read again.
class PageCache
{
    // Response header that tells if a page came from the cache.
    public const HEADER = 'X-Verso-Cache';

    /**
     * @param  list<string>  $allowedQuery
     */
    public function __construct(
        protected Cache $cache,
        protected bool $enabled = true,
        protected int $ttl = 3600,
        protected string $prefix = 'verso.page',
        protected array $allowedQuery = ['page'],
        protected string $bypassCookie = 'verso_nocache',
        protected string $cacheControl = 'public, max-age=0, must-revalidate',
    ) {}

    public function bypassCookie(): string
    {
        return $this->bypassCookie;
    }

    // True when a request may get a stored page before the session starts.
    // Signed in visitors and visitors with a notice waiting carry the bypass cookie.
    public function acceptsEarly(BaseRequest $request): bool
    {
        if (! $this->enabled() || ! $request->isMethodCacheable() || $request->cookies->has($this->bypassCookie)) {
            return false;
        }

        foreach (array_keys($request->cookies->all()) as $name) {
            if (str_starts_with((string) $name, 'remember_')) {
                return false;
            }
        }

        return array_diff(array_keys($request->query->all()), $this->allowedQuery) === [];
    }

    public function enabled(): bool
    {
        return $this->enabled && $this->ttl > 0;
    }

    // True when this request may be served from (or saved to) the cache.
    public function accepts(Request $request): bool
    {
        if (! $this->enabled() || ! $request->isMethodCacheable() || $request->user() !== null) {
            return false;
        }

        if ($this->hasFlashData($request)) {
            return false;
        }

        return array_diff(array_keys($request->query()), $this->allowedQuery) === [];
    }

    // Only plain successful pages are saved.
    public function shouldStore(Response $response): bool
    {
        return $response->getStatusCode() === 200
            && $response->getContent() !== false
            && ! str_contains((string) $response->headers->get('Cache-Control'), 'no-store');
    }

    // The stored page with its validators and cache headers, or a 304 when the browser copy is still fresh.
    public function get(BaseRequest $request): ?Response
    {
        $stored = $this->cache->get($this->key($request));

        if (! is_array($stored) || ! is_string($stored['content'] ?? null)) {
            return null;
        }

        $contentType = is_string($stored['type'] ?? null) ? $stored['type'] : 'text/html; charset=UTF-8';
        $response = new Response($stored['content'], 200, ['Content-Type' => $contentType]);

        $this->addValidators(
            $response,
            is_string($stored['etag'] ?? null) ? $stored['etag'] : $this->etag($stored['content']),
            is_int($stored['time'] ?? null) ? $stored['time'] : Carbon::now()->getTimestamp(),
        );
        $response->headers->set('Cache-Control', $this->cacheControl);
        $response->isNotModified($request);

        return $response;
    }

    // Saves the page and gives the live response the same validators.
    public function put(BaseRequest $request, Response $response): void
    {
        $content = (string) $response->getContent();
        $etag = $this->etag($content);
        $time = Carbon::now()->getTimestamp();

        $this->cache->put($this->key($request), [
            'content' => $content,
            'type' => (string) $response->headers->get('Content-Type', 'text/html; charset=UTF-8'),
            'etag' => $etag,
            'time' => $time,
        ], $this->ttl);

        $this->addValidators($response, $etag, $time);
    }

    public function etag(string $content): string
    {
        return '"'.hash('xxh128', $content).'"';
    }

    protected function addValidators(Response $response, string $etag, int $time): void
    {
        $response->headers->set('ETag', $etag);
        $response->setLastModified(Carbon::createFromTimestamp($time));
    }

    // Drop every cached page at once.
    public function flush(): void
    {
        $this->cache->forever($this->versionKey(), $this->version() + 1);
    }

    // Drop the cached copy of one URL (first page only), for changes that touch a few pages.
    public function forget(string $url): void
    {
        $this->cache->forget($this->key(BaseRequest::create($url)));
    }

    // The cache key for a request: host, path and the allowed query values.
    public function key(BaseRequest $request): string
    {
        $query = array_intersect_key($request->query->all(), array_flip($this->allowedQuery));
        ksort($query);

        $url = $request->getSchemeAndHttpHost().'/'.trim($request->getPathInfo(), '/').'?'.http_build_query($query);

        return $this->prefix.':'.$this->version().':'.sha1($url);
    }

    // A page shown right after a form post can hold a notice or errors for one visitor only.
    public function hasFlashData(Request $request): bool
    {
        return $request->hasSession() && $request->session()->get('_flash.old', []) !== [];
    }

    public function version(): int
    {
        $version = $this->cache->get($this->versionKey());

        return is_numeric($version) ? (int) $version : 1;
    }

    protected function versionKey(): string
    {
        return $this->prefix.':version';
    }
}
