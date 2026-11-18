import { describe, expect, it } from 'vitest';
import {
    NAV_WIDTH_DEFAULT,
    NAV_WIDTH_MAX,
    NAV_WIDTH_MIN,
    clampNavWidth,
    navWidthForKey,
} from '@/hooks/use-admin-nav-width';

describe('clampNavWidth', () => {
    it('keeps the width inside the limits', () => {
        expect(clampNavWidth(100)).toBe(NAV_WIDTH_MIN);
        expect(clampNavWidth(5000)).toBe(NAV_WIDTH_MAX);
        expect(clampNavWidth(300.6)).toBe(301);
    });

    it('falls back to the default for broken values', () => {
        expect(clampNavWidth(Number.NaN)).toBe(NAV_WIDTH_DEFAULT);
    });
});

describe('navWidthForKey', () => {
    it('grows with the arrow toward the content and shrinks with the other', () => {
        expect(navWidthForKey(300, 'ArrowRight', false, false)).toBe(316);
        expect(navWidthForKey(300, 'ArrowLeft', false, false)).toBe(284);
        expect(navWidthForKey(300, 'ArrowRight', true, false)).toBe(348);
    });

    it('swaps the arrows on right to left pages', () => {
        expect(navWidthForKey(300, 'ArrowLeft', false, true)).toBe(316);
        expect(navWidthForKey(300, 'ArrowRight', false, true)).toBe(284);
    });

    it('jumps to the limits with Home and End and ignores other keys', () => {
        expect(navWidthForKey(300, 'Home', false, false)).toBe(NAV_WIDTH_MIN);
        expect(navWidthForKey(300, 'End', false, false)).toBe(NAV_WIDTH_MAX);
        expect(navWidthForKey(300, 'Enter', false, false)).toBeNull();
    });
});
