<?php

namespace App\Verso\Theme\Http\Middleware;

use App\Verso\Theme\Cache\PageCache;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

// Sets a cookie for signed in visitors and for visitors with a notice waiting, so they never get a stored page.
class MarkPageCacheBypass
{
    public function __construct(protected PageCache $cache) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $name = $this->cache->bypassCookie();
        $needed = $request->user() !== null || $this->hasNewFlashData($request);

        if ($needed && ! $request->cookies->has($name)) {
            $response->headers->setCookie(new Cookie($name, '1', $this->expires(), '/', null, $request->isSecure(), true, false, Cookie::SAMESITE_LAX));
        } elseif (! $needed && $request->cookies->has($name)) {
            $response->headers->clearCookie($name, '/', null, $request->isSecure(), true, Cookie::SAMESITE_LAX);
        }

        return $response;
    }

    protected function hasNewFlashData(Request $request): bool
    {
        return $request->hasSession() && $request->session()->get('_flash.new', []) !== [];
    }

    // The cookie lives as long as a session.
    protected function expires(): int
    {
        return now()->addMinutes((int) config('session.lifetime'))->getTimestamp();
    }
}
