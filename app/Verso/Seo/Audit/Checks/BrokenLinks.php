<?php

namespace App\Verso\Seo\Audit\Checks;

use App\Verso\Seo\Audit\AuditContext;
use App\Verso\Seo\Audit\CheckResult;
use App\Verso\Seo\Audit\EntrySnapshot;
use App\Verso\Seo\Audit\IssueFix;
use App\Verso\Seo\Audit\Severity;
use App\Verso\Seo\Links\LinkStatus;
use App\Verso\Seo\Models\PageReport;
use App\Verso\Seo\Models\SeoLink;

// Links in your content that lead to pages that do not exist. Found by the link check.
class BrokenLinks extends BaseCheck
{
    // Paths listed in the detail of one page.
    protected const PATHS_SHOWN = 3;

    public function key(): string
    {
        return 'broken-links';
    }

    public function label(): string
    {
        return __('Broken links');
    }

    public function description(): string
    {
        return __('These pages link to addresses on your site that do not exist. Fix the link, or add a redirect from the old address.');
    }

    public function severity(): Severity
    {
        return Severity::Critical;
    }

    public function fix(): ?IssueFix
    {
        return IssueFix::link(__('Add a redirect'), route('admin.site.redirects.index'));
    }

    public function run(AuditContext $context): CheckResult
    {
        if (! $context->crawl->hasResults()) {
            return CheckResult::pending(message: 'Run the link check to find broken links.');
        }

        $entries = [];
        foreach ($context->entries() as $entry) {
            $entries[$entry->id] = $entry;
        }

        $broken = SeoLink::query()->where('status', LinkStatus::Broken)
            ->orderBy('source_entry_id')->orderBy('path')
            ->get(['source_entry_id', 'path'])
            ->groupBy('source_entry_id');
        $items = [];
        $affected = 0;

        foreach ($broken as $sourceId => $links) {
            $entry = $entries[(int) $sourceId] ?? null;

            if (! $entry instanceof EntrySnapshot) {
                continue;
            }

            $affected += $links->count();
            $paths = $links->pluck('path')->take(self::PATHS_SHOWN)->implode(', ');
            $more = max(0, $links->count() - self::PATHS_SHOWN);
            $items[] = $more === 0
                ? $entry->item(message: 'Links to {paths}.', values: ['paths' => $paths])
                : $entry->item(message: 'Links to {paths} and {more, plural, one {# more} other {# more}}.', values: ['paths' => $paths, 'more' => $more]);
        }

        $total = max($affected, (int) PageReport::query()->sum('internal_links'));

        return new CheckResult($affected, $total, array_slice($items, 0, $context->maxItems));
    }
}
