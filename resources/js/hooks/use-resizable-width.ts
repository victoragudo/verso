import { useCallback, useState } from 'react';

// The limits of a panel that people can make wider or narrower, in pixels.
export type WidthLimits = {
    min: number;
    max: number;
    initial: number;
    // The width is kept in this browser under this key.
    storageKey: string;
};

// Pixels moved by one arrow key press, and with Shift held.
export const WIDTH_STEP = 16;
export const WIDTH_BIG_STEP = 48;

// Keeps a width inside the limits, rounded to whole pixels.
export function clampWidth(limits: WidthLimits, width: number): number {
    if (!Number.isFinite(width)) {
        return limits.initial;
    }

    return Math.round(Math.min(limits.max, Math.max(limits.min, width)));
}

// The new width for a key press on a resize handle, or null for other keys.
// The panel sits on the start side, so in right to left pages the arrows swap.
export function widthForKey(
    limits: WidthLimits,
    width: number,
    key: string,
    shift: boolean,
    rtl: boolean,
): number | null {
    const step = shift ? WIDTH_BIG_STEP : WIDTH_STEP;
    const grow = rtl ? 'ArrowLeft' : 'ArrowRight';
    const shrink = rtl ? 'ArrowRight' : 'ArrowLeft';

    switch (key) {
        case grow:
            return clampWidth(limits, width + step);
        case shrink:
            return clampWidth(limits, width - step);
        case 'Home':
            return limits.min;
        case 'End':
            return limits.max;
        default:
            return null;
    }
}

function readStoredWidth(limits: WidthLimits): number {
    try {
        const stored = window.localStorage.getItem(limits.storageKey);

        return stored === null
            ? limits.initial
            : clampWidth(limits, Number(stored));
    } catch {
        return limits.initial;
    }
}

// The width this person chose for a panel in this browser.
export function useResizableWidth(limits: WidthLimits) {
    // The admin renders only in the browser, so the saved width is read before the first paint.
    const [width, setWidthState] = useState(() =>
        typeof window === 'undefined'
            ? limits.initial
            : readStoredWidth(limits),
    );

    const setWidth = useCallback(
        (next: number) => {
            const clamped = clampWidth(limits, next);
            setWidthState(clamped);

            try {
                window.localStorage.setItem(limits.storageKey, String(clamped));
            } catch {
                // Storage can be blocked; the width then lasts for this page only.
            }
        },
        [limits],
    );

    const reset = useCallback(
        () => setWidth(limits.initial),
        [limits, setWidth],
    );

    return { width, setWidth, reset };
}
