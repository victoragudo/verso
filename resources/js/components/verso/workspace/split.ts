// Math of the line between the block canvas and the preview. A split is the share of the width the canvas takes (0 to 1).

// Smallest width of each side, in pixels.
export const SPLIT_MIN_PANE = 320;

// How far one arrow key press moves the line, and with Shift held.
export const SPLIT_STEP = 0.02;
export const SPLIT_BIG_STEP = 0.1;

// Canvas share before people move the line: less on very wide screens, where the preview needs the room.
const DEFAULT_SPLITS: { from: number; split: number }[] = [
    { from: 2400, split: 0.4 },
    { from: 0, split: 0.46 },
];

export function defaultSplit(width: number): number {
    return (
        DEFAULT_SPLITS.find((item) => width >= item.from)?.split ??
        DEFAULT_SPLITS[DEFAULT_SPLITS.length - 1].split
    );
}

// The smallest and largest share that keep both sides at least SPLIT_MIN_PANE wide.
export function splitBounds(width: number): { min: number; max: number } {
    if (width <= SPLIT_MIN_PANE * 2) {
        return { min: 0.5, max: 0.5 };
    }

    const min = SPLIT_MIN_PANE / width;

    return { min, max: 1 - min };
}

export function clampSplit(split: number, width: number): number {
    const { min, max } = splitBounds(width);

    return Math.min(max, Math.max(min, split));
}

// The share for a pointer at clientX. In right to left pages the canvas is on the right.
export function splitFromPointer(input: {
    clientX: number;
    left: number;
    width: number;
    rtl: boolean;
}): number {
    const { clientX, left, width, rtl } = input;

    if (width <= 0) {
        return 0.5;
    }

    const fromStart = rtl ? left + width - clientX : clientX - left;

    return clampSplit(fromStart / width, width);
}

// The share after a key press, or null when the key does nothing.
export function splitFromKey(input: {
    key: string;
    split: number;
    width: number;
    rtl: boolean;
    big: boolean;
}): number | null {
    const { key, split, width, rtl, big } = input;
    const step = big ? SPLIT_BIG_STEP : SPLIT_STEP;
    const { min, max } = splitBounds(width);
    const grow = rtl ? 'ArrowLeft' : 'ArrowRight';
    const shrink = rtl ? 'ArrowRight' : 'ArrowLeft';

    switch (key) {
        case grow:
            return clampSplit(split + step, width);
        case shrink:
            return clampSplit(split - step, width);
        case 'Home':
            return min;
        case 'End':
            return max;
        case 'Enter':
            return clampSplit(defaultSplit(width), width);
        default:
            return null;
    }
}

// A saved share, or null when nothing usable was saved.
export function parseSplit(raw: string | null): number | null {
    if (raw === null) {
        return null;
    }

    const value = Number(raw);

    return Number.isFinite(value) && value > 0 && value < 1 ? value : null;
}
