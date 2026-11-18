<?php

namespace App\Verso\Seo\Audit\Checks;

use App\Verso\Seo\Audit\AuditContext;
use App\Verso\Seo\Audit\CheckResult;
use App\Verso\Seo\Audit\EntrySnapshot;
use App\Verso\Seo\Audit\IssueFix;
use App\Verso\Seo\Audit\Severity;
use App\Verso\Site\Enums\MenuItemType;
use App\Verso\Site\Models\MenuItem;

// Pages hidden from search engines that are still linked from a menu. Often a page was hidden by mistake.
class NoindexInMenus extends BaseCheck
{
    public function key(): string
    {
        return 'noindex-in-menus';
    }

    public function label(): string
    {
        return __('Hidden pages linked from menus');
    }

    public function description(): string
    {
        return __('These pages are in a menu, so they matter to visitors, but they are hidden from search engines. Check that this is what you want.');
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function fix(): ?IssueFix
    {
        return IssueFix::link(__('Open menus'), route('admin.site.menus.index'));
    }

    public function run(AuditContext $context): CheckResult
    {
        $hidden = [];
        foreach ($context->entries() as $entry) {
            if ($entry->noindex) {
                $hidden[$entry->id] = $entry;
            }
        }

        if ($hidden === []) {
            return CheckResult::passed();
        }

        $inMenus = MenuItem::query()
            ->where('type', MenuItemType::Entry)
            ->whereIn('entry_id', array_keys($hidden))
            ->with('menu:id,name')
            ->get(['id', 'menu_id', 'entry_id']);

        $items = [];
        foreach ($inMenus->groupBy('entry_id') as $entryId => $menuItems) {
            $names = $menuItems->map(fn (MenuItem $item): string => $item->menu->name)->filter()->unique()->implode(', ');
            /** @var EntrySnapshot $entry */
            $entry = $hidden[(int) $entryId];
            $items[] = $entry->item(message: 'Hidden from search engines, but linked from: {menus}.', values: ['menus' => $names]);
        }

        return CheckResult::of($items, max(1, count($items)), $context->maxItems);
    }
}
