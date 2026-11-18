import { useCallback, useState } from 'react';
import { useMediaQuery } from '@/hooks/use-media-query';

export const EDITOR_SIDEBAR_KEY = 'verso:editor-sidebar';

// From this width the settings sidebar sits next to the canvas. Below it, it opens as a sheet.
export const WIDE_EDITOR_QUERY = '(min-width: 1024px)';

// The saved open or closed choice. Storage can be missing or blocked, so errors mean "open".
function readChoice(key: string): boolean {
    try {
        return window.localStorage.getItem(key) !== 'closed';
    } catch {
        return true;
    }
}

function saveChoice(key: string, open: boolean): void {
    try {
        window.localStorage.setItem(key, open ? 'open' : 'closed');
    } catch {
        return;
    }
}

// Open state of the editor settings sidebar. Wide screens remember it per user, the sheet on small screens starts closed.
export function useEditorSidebar(userId: number | string | null | undefined) {
    const key = `${EDITOR_SIDEBAR_KEY}:${userId ?? 'guest'}`;
    const wide = useMediaQuery(WIDE_EDITOR_QUERY);
    const [pinned, setPinned] = useState(() => readChoice(key));
    const [sheetOpen, setSheetOpen] = useState(false);

    const setOpen = useCallback(
        (open: boolean) => {
            if (wide) {
                setPinned(open);
                saveChoice(key, open);
            } else {
                setSheetOpen(open);
            }
        },
        [wide, key],
    );

    const toggle = useCallback(() => {
        if (wide) {
            setPinned((current) => {
                saveChoice(key, !current);

                return !current;
            });
        } else {
            setSheetOpen((current) => !current);
        }
    }, [wide, key]);

    return { wide, open: wide ? pinned : sheetOpen, setOpen, toggle };
}
