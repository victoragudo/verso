<?php

namespace App\Verso\Seo\NotFound;

use App\Verso\Core\Models\Redirect;
use App\Verso\Seo\Models\NotFoundHit;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

// Counts public addresses that answered 404. It keeps only the path, a hit count, dates and the referrer host.
// The IP address is never stored: a hash of it is only used as a short lived rate limit key in the cache.
class NotFoundMonitor
{
    public const GLOBAL_KEY = 'verso-404:site';

    public const VISITOR_KEY = 'verso-404:visitor:';

    // The request to count once the response was sent.
    protected ?Request $pending = null;

    // Paths that belong to the admin or to internal endpoints.
    protected const PRIVATE_PATHS = ['admin', 'admin/*', '_verso/*', 'livewire/*', 'up'];

    /**
     * @param  list<string>  $ignore  path patterns that are never counted ("*" is a wildcard)
     */
    public function __construct(
        protected RateLimiter $limiter,
        protected bool $enabled,
        protected int $maxRows,
        protected int $perMinute,
        protected int $perVisitorPerMinute,
        protected int $pathMaxLength,
        protected int $retentionDays,
        protected array $ignore,
        protected string $secret,
    ) {}

    // True when this response is a public page that was not found.
    public function shouldRecord(Request $request, Response $response): bool
    {
        if (! $this->enabled || $response->getStatusCode() !== 404 || ! $request->isMethod('GET')) {
            return false;
        }

        if ($request->expectsJson() || $request->header('X-Inertia') !== null || $request->is(...self::PRIVATE_PATHS)) {
            return false;
        }

        return ! $this->ignored($this->normalize($request->path()));
    }

    // Keeps the request to count it after the response, so visitors never wait for it.
    public function queue(Request $request): void
    {
        $this->pending = $request;
    }

    public function flush(): void
    {
        $request = $this->pending;
        $this->pending = null;

        if ($request !== null) {
            $this->record($request);
        }
    }

    // Counts one hit. Returns false when the hit was not counted (rate limit, full list or ignored path).
    public function record(Request $request): bool
    {
        $path = $this->normalize($request->path());

        if ($this->ignored($path) || ! $this->withinLimits($request)) {
            return false;
        }

        $now = Carbon::now();
        $referrer = $this->referrerHost($request);
        $updated = NotFoundHit::query()->where('path', $path)->increment('hits', 1, array_filter([
            'last_seen_at' => $now,
            'referrer_host' => $referrer,
        ]));

        if ($updated > 0) {
            return true;
        }

        if (NotFoundHit::query()->count() >= $this->maxRows) {
            return false;
        }

        NotFoundHit::query()->insertOrIgnore([
            'path' => $path,
            'hits' => 1,
            'referrer_host' => $referrer,
            'first_seen_at' => $now,
            'last_seen_at' => $now,
        ]);

        return true;
    }

    // Deletes addresses that were not asked for during the retention time.
    public function prune(): int
    {
        return NotFoundHit::query()
            ->where('last_seen_at', '<', Carbon::now()->subDays(max(1, $this->retentionDays)))
            ->delete();
    }

    // The same shape as redirect paths ("/old-post"), cut to the longest length kept.
    public function normalize(string $path): string
    {
        return Str::limit(Redirect::normalizePath($path), max(1, $this->pathMaxLength), '');
    }

    public function ignored(string $path): bool
    {
        foreach ($this->ignore as $pattern) {
            $pattern = trim($pattern);

            if ($pattern !== '' && (Str::is($pattern, $path) || Str::is($pattern, ltrim($path, '/')))) {
                return true;
            }
        }

        return false;
    }

    protected function withinLimits(Request $request): bool
    {
        $visitor = self::VISITOR_KEY.hash_hmac('sha256', (string) $request->ip(), $this->secret);

        if ($this->limiter->tooManyAttempts($visitor, max(1, $this->perVisitorPerMinute))
            || $this->limiter->tooManyAttempts(self::GLOBAL_KEY, max(1, $this->perMinute))) {
            return false;
        }

        $this->limiter->hit($visitor, 60);
        $this->limiter->hit(self::GLOBAL_KEY, 60);

        return true;
    }

    // Only the host of the page that linked here, never the full address.
    protected function referrerHost(Request $request): ?string
    {
        $host = parse_url((string) $request->headers->get('referer'), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? Str::limit(mb_strtolower($host), 255, '') : null;
    }
}
