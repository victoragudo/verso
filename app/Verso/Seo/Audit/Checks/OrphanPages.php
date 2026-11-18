<?php

namespace App\Verso\Seo\Audit\Checks;

use App\Verso\Seo\Audit\AuditContext;
use App\Verso\Seo\Audit\CheckResult;
use App\Verso\Seo\Audit\Severity;
use App\Verso\Seo\Links\LinkStatus;
use App\Verso\Seo\Models\SeoLink;
use App\Verso\Site\Enums\MenuItemType;
use App\Verso\Site\Models\MenuItem;

// Pages that no other page and no menu links to. Visitors and search engines find them only by chance.
class OrphanPages extends BaseCheck
{
    public function key(): string
    {
        return 'orphan-pages';
    }

    public function label(): string
    {
        return __('Pages with no links to them');
    }

    public function description(): string
    {
        return __('No other page and no menu links to these pages. Link to them from related posts or add them to a menu.');
    }

    public function severity(): Severity
    {
        return Severity::Notice;
    }

    public function run(AuditContext $context): CheckResult
    {
        if (! $context->crawl->hasResults()) {
            return CheckResult::pending(message: 'Run the link check to find pages with no links to them.');
        }

        $linked = SeoLink::query()->where('status', LinkStatus::Ok)->whereNotNull('target_entry_id')
            ->distinct()->pluck('target_entry_id')->all();
        $inMenus = MenuItem::query()->where('type', MenuItemType::Entry)->whereNotNull('entry_id')
            ->distinct()->pluck('entry_id')->all();
        $known = array_fill_keys(array_map(intval(...), [...$linked, ...$inMenus]), true);

        $candidates = array_values(array_filter($context->indexableEntries(), fn ($entry): bool => ! $entry->isFrontPage));
        $items = [];

        foreach ($candidates as $entry) {
            if (! isset($known[$entry->id])) {
                $items[] = $entry->item(message: 'No page or menu links here.');
            }
        }

        return CheckResult::of($items, count($candidates), $context->maxItems);
    }
}
