import { msg, t } from '@/lib/i18n';
import type { DashboardPanel, DashboardStat, VersoNavItem } from '@/types';

// The dashboard in simple mode: only numbers and lists that lead to pages in the menu, with plain names.

// Plain names for dashboard parts whose usual name is technical, by key. Translated when shown.
export const PLAIN_DASHBOARD_LABELS: Record<string, string> = {
    'recent-entries': msg('Latest pages and posts'),
    media: msg('Photos and files'),
    drafts: msg('Not published yet'),
};

export const PLAIN_EMPTY_TEXTS: Record<string, string> = {
    'recent-entries': msg(
        'Nothing written yet. Your new pages and posts show here.',
    ),
};

// The plain text for a key translated, or the text the server sent (already translated).
function plain(texts: Record<string, string>, key: string, fallback: string) {
    return texts[key] ? t(texts[key]) : fallback;
}

function pathOf(href: string): string {
    return href.split(/[?#]/)[0].replace(/\/+$/, '') || '/';
}

// True when the link leads to a page the menu shows, or when there is no link.
export function leadsToMenuPage(
    href: string | null,
    nav: VersoNavItem[],
): boolean {
    if (href === null) {
        return true;
    }

    const path = pathOf(href);

    return nav.some((item) => pathOf(item.href) === path);
}

// Numbers stay only when they open a page of the menu, so "Users" and other counts without a page go.
export function simpleStats(
    stats: DashboardStat[],
    nav: VersoNavItem[],
): DashboardStat[] {
    return stats
        .filter((stat) => stat.href !== null && leadsToMenuPage(stat.href, nav))
        .map((stat) => ({
            ...stat,
            label: plain(PLAIN_DASHBOARD_LABELS, stat.key, stat.label),
        }));
}

export function simplePanels(
    panels: DashboardPanel[],
    nav: VersoNavItem[],
): DashboardPanel[] {
    return panels
        .filter((panel) => leadsToMenuPage(panel.href, nav))
        .map((panel) => ({
            ...panel,
            label: plain(PLAIN_DASHBOARD_LABELS, panel.key, panel.label),
            emptyText: plain(PLAIN_EMPTY_TEXTS, panel.key, panel.emptyText),
        }));
}
