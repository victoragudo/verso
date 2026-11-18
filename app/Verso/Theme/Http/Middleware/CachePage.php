<?php

namespace App\Verso\Theme\Http\Middleware;

use App\Verso\Theme\Cache\PageCache;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Serves public pages to guests from the page cache, and saves pages that are not cached yet.
// Most hits are already answered by ServeCachedPage before the session starts.
class CachePage
{
    public function __construct(protected PageCache $cache) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->cache->accepts($request)) {
            return $next($request);
        }

        $cached = $this->cache->get($request);

        if ($cached !== null) {
            $cached->headers->set(PageCache::HEADER, 'HIT');

            return $cached;
        }

        /** @var Response $response */
        $response = $next($request);

        if ($this->cache->shouldStore($response)) {
            $this->cache->put($request, $response);
            $response->headers->set(PageCache::HEADER, 'MISS');
        }

        return $response;
    }
}
