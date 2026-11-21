import {
    clampWidth,
    useResizableWidth,
    widthForKey,
    WIDTH_BIG_STEP,
    WIDTH_STEP,
} from '@/hooks/use-resizable-width';
import type { WidthLimits } from '@/hooks/use-resizable-width';

// Width of the admin sidebar in pixels: the default and the limits of a resize.
export const NAV_WIDTH_DEFAULT = 288;
export const NAV_WIDTH_MIN = 240;
export const NAV_WIDTH_MAX = 448;

export const NAV_WIDTH_STEP = WIDTH_STEP;
export const NAV_WIDTH_BIG_STEP = WIDTH_BIG_STEP;

export const NAV_WIDTH_STORAGE_KEY = 'verso.admin.nav-width';

export const NAV_WIDTH_LIMITS: WidthLimits = {
    min: NAV_WIDTH_MIN,
    max: NAV_WIDTH_MAX,
    initial: NAV_WIDTH_DEFAULT,
    storageKey: NAV_WIDTH_STORAGE_KEY,
};

// Keeps a width inside the limits, rounded to whole pixels.
export function clampNavWidth(width: number): number {
    return clampWidth(NAV_WIDTH_LIMITS, width);
}

// The new width for a key press on the resize handle, or null for other keys.
// In right to left pages the sidebar is on the right, so the arrows swap.
export function navWidthForKey(
    width: number,
    key: string,
    shift: boolean,
    rtl: boolean,
): number | null {
    return widthForKey(NAV_WIDTH_LIMITS, width, key, shift, rtl);
}

// The sidebar width chosen by this person in this browser.
export function useAdminNavWidth() {
    return useResizableWidth(NAV_WIDTH_LIMITS);
}
