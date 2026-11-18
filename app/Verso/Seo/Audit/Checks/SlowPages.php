<?php

namespace App\Verso\Seo\Audit\Checks;

use App\Verso\Seo\Audit\AuditContext;
use App\Verso\Seo\Audit\CheckResult;
use App\Verso\Seo\Audit\Severity;
use App\Verso\Seo\Models\PageReport;

// Pages whose content takes long to build on the server, measured by the link check.
class SlowPages extends BaseCheck
{
    public function __construct(protected int $slowMs) {}

    public function key(): string
    {
        return 'slow-pages';
    }

    public function label(): string
    {
        return __('Slow pages');
    }

    public function description(): string
    {
        return __('The content of these pages took more than :ms ms to build. The page cache hides it for most visitors, but search engines may see slow first loads. Large embeds, many images or heavy plugin blocks are the usual cause.', ['ms' => $this->slowMs]);
    }

    public function severity(): Severity
    {
        return Severity::Notice;
    }

    public function run(AuditContext $context): CheckResult
    {
        if (! $context->crawl->hasResults()) {
            return CheckResult::pending(message: 'Run the link check to measure your pages.');
        }

        $times = PageReport::query()->pluck('render_ms', 'entry_id')->all();
        $items = [];

        foreach ($context->entries() as $entry) {
            $ms = (int) ($times[$entry->id] ?? 0);

            if ($ms > $this->slowMs) {
                $items[] = $entry->item(message: 'Content took {ms, number} ms to build.', values: ['ms' => $ms]);
            }
        }

        return CheckResult::of($items, max(1, count($times)), $context->maxItems);
    }
}
