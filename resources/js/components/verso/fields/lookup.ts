import { t } from '@/lib/i18n';
import { lookup } from '@/routes/admin/fields';
import type { MediaLibraryItem } from '@/types';
import type { ChoiceOption } from './types';

export type LookupKind = 'entry' | 'term' | 'user';

export type LookupFilters = {
    types?: string[];
    taxonomy?: string | null;
    roles?: string[];
};

export type LookupItem = ChoiceOption & { hint?: string };

async function request<T>(
    query: Record<string, string | string[]>,
    signal?: AbortSignal,
): Promise<T[]> {
    const response = await fetch(lookup.url({ query }), {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        signal,
    });

    if (!response.ok) {
        throw new Error(t('Could not load this list.'));
    }

    return ((await response.json()) as { items: T[] }).items;
}

function filterQuery(
    filters: LookupFilters,
): Record<string, string | string[]> {
    return {
        ...(filters.types?.length ? { 'types[]': filters.types } : {}),
        ...(filters.taxonomy ? { taxonomy: filters.taxonomy } : {}),
        ...(filters.roles?.length ? { 'roles[]': filters.roles } : {}),
    };
}

// Asks the server for entries, terms or users whose name matches the text.
export function searchItems(
    kind: LookupKind,
    text: string,
    filters: LookupFilters,
    signal?: AbortSignal,
): Promise<LookupItem[]> {
    return request<LookupItem>(
        { kind, q: text, ...filterQuery(filters) },
        signal,
    );
}

// Labels of items that are already picked.
export function itemsById(
    kind: LookupKind,
    ids: number[],
    filters: LookupFilters,
): Promise<LookupItem[]> {
    if (ids.length === 0) {
        return Promise.resolve([]);
    }

    return request<LookupItem>({
        kind,
        'ids[]': ids.map(String),
        ...filterQuery(filters),
    });
}

// Media items (with thumbnails) that are already picked.
export function mediaById(ids: number[]): Promise<MediaLibraryItem[]> {
    if (ids.length === 0) {
        return Promise.resolve([]);
    }

    return request<MediaLibraryItem>({
        kind: 'media',
        'ids[]': ids.map(String),
    });
}
