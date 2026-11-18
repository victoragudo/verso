import { t } from '@/lib/i18n';
import { search } from '@/routes/admin/site/menus';
import type { MenuItemType, MenuSourceItem } from '@/types';

export type MenuSearchResult = {
    items: MenuSourceItem[];
    hasMore: boolean;
};

// Asks the server for published entries or terms whose title matches the query.
export async function searchMenuSource(
    kind: Exclude<MenuItemType, 'custom'>,
    source: string,
    query: string,
    signal?: AbortSignal,
): Promise<MenuSearchResult> {
    const response = await fetch(
        search.url({ query: { kind, source, q: query } }),
        {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal,
        },
    );

    if (!response.ok) {
        throw new Error(t('Could not search this list.'));
    }

    return (await response.json()) as MenuSearchResult;
}
