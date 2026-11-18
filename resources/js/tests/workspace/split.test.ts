import { describe, expect, it } from 'vitest';
import {
    SPLIT_BIG_STEP,
    SPLIT_MIN_PANE,
    SPLIT_STEP,
    clampSplit,
    defaultSplit,
    parseSplit,
    splitBounds,
    splitFromKey,
    splitFromPointer,
} from '@/components/verso/workspace/split';

describe('split math', () => {
    it('gives the canvas less room on very wide screens', () => {
        expect(defaultSplit(3000)).toBeLessThan(defaultSplit(1200));
    });

    it('keeps both sides at least the smallest pane width', () => {
        const width = 1600;
        const { min, max } = splitBounds(width);

        expect(min * width).toBeCloseTo(SPLIT_MIN_PANE);
        expect((1 - max) * width).toBeCloseTo(SPLIT_MIN_PANE);
        expect(clampSplit(0.01, width)).toBe(min);
        expect(clampSplit(0.99, width)).toBe(max);
    });

    it('splits in half when the space is too small for two panes', () => {
        expect(splitBounds(500)).toEqual({ min: 0.5, max: 0.5 });
    });

    it('reads the pointer from the start side, also in right to left pages', () => {
        const box = { left: 100, width: 1000 };

        expect(
            splitFromPointer({ ...box, clientX: 500, rtl: false }),
        ).toBeCloseTo(0.4);
        expect(
            splitFromPointer({ ...box, clientX: 500, rtl: true }),
        ).toBeCloseTo(0.6);
    });

    it('moves with the arrow keys, Shift for bigger steps, Home and End for the ends', () => {
        const base = { split: 0.5, width: 2000, big: false };

        expect(
            splitFromKey({ ...base, key: 'ArrowRight', rtl: false }),
        ).toBeCloseTo(0.5 + SPLIT_STEP);
        expect(
            splitFromKey({ ...base, key: 'ArrowLeft', rtl: false }),
        ).toBeCloseTo(0.5 - SPLIT_STEP);
        expect(
            splitFromKey({ ...base, key: 'ArrowRight', rtl: false, big: true }),
        ).toBeCloseTo(0.5 + SPLIT_BIG_STEP);
        expect(splitFromKey({ ...base, key: 'Home', rtl: false })).toBe(
            splitBounds(2000).min,
        );
        expect(splitFromKey({ ...base, key: 'End', rtl: false })).toBe(
            splitBounds(2000).max,
        );
        expect(splitFromKey({ ...base, key: 'Tab', rtl: false })).toBeNull();
    });

    it('flips the arrow keys in right to left pages', () => {
        expect(
            splitFromKey({
                key: 'ArrowLeft',
                split: 0.5,
                width: 2000,
                rtl: true,
                big: false,
            }),
        ).toBeCloseTo(0.5 + SPLIT_STEP);
    });

    it('ignores saved values that are not a share', () => {
        expect(parseSplit('0.42')).toBe(0.42);
        expect(parseSplit('7')).toBeNull();
        expect(parseSplit('wide')).toBeNull();
        expect(parseSplit(null)).toBeNull();
    });
});
