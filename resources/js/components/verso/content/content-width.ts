import { useSyncExternalStore } from 'react';
import { msg } from '@/lib/i18n';

// How wide the editor canvas shows the page: the whole canvas, or a narrower column that is easier to read.
export type ContentWidth = 'full' | 'reading';

export const CONTENT_WIDTHS: { value: ContentWidth; label: string }[] = [
    { value: 'full', label: msg('Full width') },
    { value: 'reading', label: msg('Reading width') },
];

// Key in localStorage that keeps the choice of this person in this browser.
export const CONTENT_WIDTH_KEY = 'verso.editor.contentWidth';

const DEFAULT_WIDTH: ContentWidth = 'full';
const listeners = new Set<() => void>();

// Storage can be blocked (private windows, strict settings). Then the default is used and nothing breaks.
function readWidth(): ContentWidth {
    try {
        const stored = window.localStorage.getItem(CONTENT_WIDTH_KEY);

        return stored === 'reading' ? 'reading' : DEFAULT_WIDTH;
    } catch {
        return DEFAULT_WIDTH;
    }
}

let current: ContentWidth | null = null;

export function setContentWidth(width: ContentWidth): void {
    current = width;

    try {
        window.localStorage.setItem(CONTENT_WIDTH_KEY, width);
    } catch {
        // The choice still works until the page is closed.
    }

    listeners.forEach((listener) => listener());
}

function subscribe(listener: () => void) {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
}

export function useContentWidth(): [
    ContentWidth,
    (width: ContentWidth) => void,
] {
    const width = useSyncExternalStore(
        subscribe,
        () => (current ??= readWidth()),
        () => DEFAULT_WIDTH,
    );

    return [width, setContentWidth];
}

// Only for tests: forget the value read from storage.
export function resetContentWidth(): void {
    current = null;
}
