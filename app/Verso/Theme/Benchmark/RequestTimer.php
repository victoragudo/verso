<?php

namespace App\Verso\Theme\Benchmark;

use App\Verso\Theme\Cache\PageCache;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

// Sends requests through the HTTP kernel in this process and measures the time to a full response.
class RequestTimer
{
    public function __construct(protected Application $app) {}

    // Turns the page cache on or off for the next requests.
    public function usePageCache(bool $enabled): void
    {
        config(['verso.page_cache.enabled' => $enabled]);
        $this->app->forgetInstance(PageCache::class);

        if ($enabled) {
            $this->app->make(PageCache::class)->flush();
        }
    }

    /**
     * Sends the warm up requests, then times the measured ones.
     *
     * @param  string|null  $expectedCacheHeader  "HIT" to check every measured response came from the page cache
     */
    public function measure(string $path, int $requests, int $warmup, ?string $expectedCacheHeader = null): Samples
    {
        for ($i = 0; $i < $warmup; $i++) {
            $this->send($path);
        }

        $times = [];

        for ($i = 0; $i < max(1, $requests); $i++) {
            $started = hrtime(true);
            $response = $this->send($path);
            $times[] = (hrtime(true) - $started) / 1_000_000;

            if ($expectedCacheHeader !== null && $response->headers->get(PageCache::HEADER) !== $expectedCacheHeader) {
                throw new RuntimeException("[{$path}] was not served from the page cache.");
            }
        }

        return new Samples($times);
    }

    // One full request cycle, like PHP-FPM runs it, without booting the framework again.
    public function send(string $path): Response
    {
        $kernel = $this->app->make(Kernel::class);
        $request = Request::create(rtrim((string) config('app.url'), '/').$path, 'GET', server: ['HTTP_ACCEPT' => 'text/html']);

        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        $this->app->forgetScopedInstances();

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException("[{$path}] answered {$response->getStatusCode()} instead of 200.");
        }

        return $response;
    }
}
