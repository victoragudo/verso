import { useCallback, useEffect, useState } from 'react';

// What the user closed or opened in the sidebar.
export type NavOpenState = {
    // Sections the user closed. Sections are open by default.
    closedGroups: string[];
    // Items with children that the user opened. They are closed by default.
    openItems: string[];
};

const EMPTY: NavOpenState = { closedGroups: [], openItems: [] };

export function navStorageKey(userId: number | string | null | undefined) {
    return `verso.nav.${userId ?? 'guest'}`;
}

// Reads the saved state. Storage can be missing or blocked, so every call is guarded.
export function readNavState(key: string): NavOpenState {
    try {
        const raw = window.localStorage.getItem(key);
        const data = raw ? JSON.parse(raw) : null;

        return {
            closedGroups: Array.isArray(data?.closedGroups)
                ? data.closedGroups.filter(
                      (v: unknown) => typeof v === 'string',
                  )
                : [],
            openItems: Array.isArray(data?.openItems)
                ? data.openItems.filter((v: unknown) => typeof v === 'string')
                : [],
        };
    } catch {
        return EMPTY;
    }
}

function writeNavState(key: string, state: NavOpenState) {
    try {
        window.localStorage.setItem(key, JSON.stringify(state));
    } catch {
        // Private windows may block storage. The sidebar still works, it just forgets.
    }
}

function toggle(list: string[], value: string, on: boolean): string[] {
    const without = list.filter((item) => item !== value);

    return on ? [...without, value] : without;
}

// Open and closed sections and items, remembered per user.
// The section and parents of the current page are opened when the page changes.
export function useNavState(
    storageKey: string,
    activeGroup: string | null,
    activeParents: string[],
) {
    const [state, setState] = useState<NavOpenState>(() =>
        readNavState(storageKey),
    );

    const update = useCallback(
        (change: (current: NavOpenState) => NavOpenState) => {
            setState((current) => {
                const next = change(current);
                writeNavState(storageKey, next);

                return next;
            });
        },
        [storageKey],
    );

    const parentsKey = activeParents.join('|');

    useEffect(() => {
        if (activeGroup === null && parentsKey === '') {
            return;
        }

        update((current) => {
            const parents = parentsKey === '' ? [] : parentsKey.split('|');
            const closedGroups = current.closedGroups.filter(
                (group) => group !== activeGroup,
            );
            const openItems = [
                ...current.openItems.filter((key) => !parents.includes(key)),
                ...parents,
            ];

            if (
                closedGroups.length === current.closedGroups.length &&
                parents.every((key) => current.openItems.includes(key))
            ) {
                return current;
            }

            return { closedGroups, openItems };
        });
    }, [activeGroup, parentsKey, update]);

    return {
        isGroupOpen: (group: string) => !state.closedGroups.includes(group),
        isItemOpen: (key: string) => state.openItems.includes(key),
        setGroupOpen: (group: string, open: boolean) =>
            update((current) => ({
                ...current,
                closedGroups: toggle(current.closedGroups, group, !open),
            })),
        setItemOpen: (key: string, open: boolean) =>
            update((current) => ({
                ...current,
                openItems: toggle(current.openItems, key, open),
            })),
        openPath: (group: string, parents: string[]) =>
            update((current) => ({
                closedGroups: current.closedGroups.filter((g) => g !== group),
                openItems: [
                    ...current.openItems.filter((k) => !parents.includes(k)),
                    ...parents,
                ],
            })),
    };
}
