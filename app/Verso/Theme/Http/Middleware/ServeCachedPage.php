<?php

namespace App\Verso\Theme\Http\Middleware;

use App\Verso\Theme\Cache\PageCache;
use Closure;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Runs before the web middleware: a stored page is sent without starting the session, cookies or Inertia.
class ServeCachedPage
{
    public function __construct(protected PageCache $cache, protected Auth $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->cache->acceptsEarly($request) || $this->auth->guard()->hasUser()) {
            return $next($request);
        }

        $cached = $this->cache->get($request);

        if ($cached === null) {
            return $next($request);
        }

        $cached->headers->set(PageCache::HEADER, 'HIT');

        return $cached;
    }
}
