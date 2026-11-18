<?php

namespace App\Verso\Seo\Audit;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Carbon;
use Throwable;

// Runs every registered check and keeps the report in the cache for a short time.
class SeoAudit
{
    public const CACHE_KEY = 'verso.seo.audit';

    /**
     * @param  Closure(): AuditContext  $context
     */
    public function __construct(
        protected SeoCheckRegistry $checks,
        protected Repository $cache,
        protected Closure $context,
        protected int $ttl,
    ) {}

    public function report(bool $fresh = false): AuditReport
    {
        if ($fresh) {
            $this->flush();
        }

        $cached = $this->cache->get(self::CACHE_KEY);

        if (is_array($cached)) {
            return AuditReport::fromArray($cached);
        }

        $report = $this->run();
        $this->cache->put(self::CACHE_KEY, $report->toArray(), max(1, $this->ttl));

        return $report;
    }

    public function run(): AuditReport
    {
        $context = ($this->context)();
        $results = [];

        foreach ($this->checks->all() as $check) {
            try {
                $results[$check->key()] = $check->run($context);
            } catch (Throwable $error) {
                // A broken plugin check must not break the page.
                report($error);
                $results[$check->key()] = CheckResult::pending(message: 'This check could not run.');
            }
        }

        $ratings = ['good' => 0, 'ok' => 0, 'poor' => 0, 'none' => 0];
        foreach ($context->entries() as $entry) {
            match ($entry->rating) {
                'good' => $ratings['good']++,
                'ok' => $ratings['ok']++,
                'poor' => $ratings['poor']++,
                default => $ratings['none']++,
            };
        }

        return new AuditReport($results, Carbon::now()->toIso8601String(), $ratings);
    }

    public function score(?AuditReport $report = null): int
    {
        return ($report ?? $this->report())->score($this->checks->all());
    }

    public function flush(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }
}
