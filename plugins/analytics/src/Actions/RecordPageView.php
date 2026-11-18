<?php

namespace Verso\Plugins\Analytics\Actions;

use Verso\Plugins\Analytics\Enums\Device;
use Verso\Plugins\Analytics\Models\AnalyticsEvent;
use Verso\Plugins\Analytics\Support\AnalyticsClock;
use Verso\Plugins\Analytics\Support\BotDetector;
use Verso\Plugins\Analytics\Support\PageView;
use Verso\Plugins\Analytics\Support\PathExclusions;
use Verso\Plugins\Analytics\Support\VisitorHasher;

// Saves one anonymous page view, unless the visitor opted out, is a bot or sent bad data.
class RecordPageView
{
    // Letters, digits, dots and dashes, with an optional port.
    protected const HOST_PATTERN = '/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?(:\d{1,5})?$/';

    public function __construct(
        protected BotDetector $bots,
        protected VisitorHasher $hasher,
        protected AnalyticsClock $clock,
        protected PathExclusions $exclusions,
        protected int $maxPathLength,
    ) {}

    // Returns true when the view was saved.
    public function handle(PageView $view): bool
    {
        if ($view->optedOut || $this->bots->isBot($view->userAgent)) {
            return false;
        }

        $path = $this->cleanPath($view->path);
        $device = Device::tryFrom((string) $view->device);

        if ($path === null || $device === null) {
            return false;
        }

        $day = $this->clock->today();

        AnalyticsEvent::query()->create([
            'day' => $day,
            'path' => $path,
            'referrer_host' => $this->cleanReferrer($view->referrerHost, $view->site),
            'device' => $device->value,
            'visitor' => $this->hasher->hash($day, $view->ip, $view->userAgent, $view->site),
            'created_at' => now(),
        ]);

        return true;
    }

    // A path like "/blog/hello". The query string and fragment are dropped, a trailing slash too.
    // Private paths, secret tokens and links with sensitive query keys give null.
    public function cleanPath(string $path): ?string
    {
        $path = trim($path);
        $query = (string) parse_url('http://site.invalid'.(str_starts_with($path, '/') ? $path : '/'), PHP_URL_QUERY);
        $path = (string) preg_replace('/[?#].*$/s', '', $path);

        if (! str_starts_with($path, '/') || str_starts_with($path, '//') || preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            return null;
        }

        $path = $path === '/' ? '/' : rtrim($path, '/');

        if ($path === '' || mb_strlen($path) > $this->maxPathLength) {
            return null;
        }

        return $this->exclusions->excludes($path, $query) ? null : $path;
    }

    // The host of another site that linked here, or null for direct visits and links inside the site.
    public function cleanReferrer(?string $host, string $site): ?string
    {
        $host = strtolower(trim((string) $host));

        if ($host === '' || strlen($host) > 255 || preg_match(self::HOST_PATTERN, $host) !== 1) {
            return null;
        }

        $bareHost = (string) preg_replace('/:\d+$/', '', $host);

        return $bareHost === strtolower($site) ? null : $host;
    }
}
