import { useCallback, useState } from 'react';

// Width of the admin sidebar in pixels: the default and the limits of a resize.
export const NAV_WIDTH_DEFAULT = 288;
export const NAV_WIDTH_MIN = 240;
export const NAV_WIDTH_MAX = 448;

// Pixels moved by one arrow key press, and with Shift held.
export const NAV_WIDTH_STEP = 16;
export const NAV_WIDTH_BIG_STEP = 48;

export const NAV_WIDTH_STORAGE_KEY = 'verso.admin.nav-width';

// Keeps a width inside the limits, rounded to whole pixels.
export function clampNavWidth(width: number): number {
    if (!Number.isFinite(width)) {
        return NAV_WIDTH_DEFAULT;
    }

    return Math.round(Math.min(NAV_WIDTH_MAX, Math.max(NAV_WIDTH_MIN, width)));
}

// The new width for a key press on the resize handle, or null for other keys.
// In right to left pages the sidebar is on the right, so the arrows swap.
export function navWidthForKey(
    width: number,
    key: string,
    shift: boolean,
    rtl: boolean,
): number | null {
    const step = shift ? NAV_WIDTH_BIG_STEP : NAV_WIDTH_STEP;
    const grow = rtl ? 'ArrowLeft' : 'ArrowRight';
    const shrink = rtl ? 'ArrowRight' : 'ArrowLeft';

    switch (key) {
        case grow:
            return clampNavWidth(width + step);
        case shrink:
            return clampNavWidth(width - step);
        case 'Home':
            return NAV_WIDTH_MIN;
        case 'End':
            return NAV_WIDTH_MAX;
        default:
            return null;
    }
}

function readStoredWidth(): number {
    try {
        const stored = window.localStorage.getItem(NAV_WIDTH_STORAGE_KEY);

        return stored === null
            ? NAV_WIDTH_DEFAULT
            : clampNavWidth(Number(stored));
    } catch {
        return NAV_WIDTH_DEFAULT;
    }
}

// The sidebar width chosen by this person in this browser.
export function useAdminNavWidth() {
    // The admin renders only in the browser, so the saved width is read before the first paint.
    const [width, setWidthState] = useState(() =>
        typeof window === 'undefined' ? NAV_WIDTH_DEFAULT : readStoredWidth(),
    );

    const setWidth = useCallback((next: number) => {
        const clamped = clampNavWidth(next);
        setWidthState(clamped);

        try {
            window.localStorage.setItem(NAV_WIDTH_STORAGE_KEY, String(clamped));
        } catch {
            // Storage can be blocked; the width then lasts for this page only.
        }
    }, []);

    const reset = useCallback(() => setWidth(NAV_WIDTH_DEFAULT), [setWidth]);

    return { width, setWidth, reset };
}
