import { describe, expect, it } from 'vitest';
import { SIDE_PANEL_WIDTH } from '@/components/verso/editor/editor-side-panel';
import { clampWidth, widthForKey } from '@/hooks/use-resizable-width';

describe('the resizable editor panel', () => {
    it('keeps the width between its limits', () => {
        expect(clampWidth(SIDE_PANEL_WIDTH, 100)).toBe(288);
        expect(clampWidth(SIDE_PANEL_WIDTH, 2000)).toBe(640);
        expect(clampWidth(SIDE_PANEL_WIDTH, 401.6)).toBe(402);
        expect(clampWidth(SIDE_PANEL_WIDTH, Number.NaN)).toBe(320);
    });

    it('grows and shrinks with the arrow keys, swapped in right to left pages', () => {
        expect(
            widthForKey(SIDE_PANEL_WIDTH, 320, 'ArrowRight', false, false),
        ).toBe(336);
        expect(
            widthForKey(SIDE_PANEL_WIDTH, 320, 'ArrowLeft', true, false),
        ).toBe(288);
        expect(
            widthForKey(SIDE_PANEL_WIDTH, 320, 'ArrowLeft', false, true),
        ).toBe(336);
        expect(widthForKey(SIDE_PANEL_WIDTH, 320, 'End', false, false)).toBe(
            640,
        );
        expect(
            widthForKey(SIDE_PANEL_WIDTH, 320, 'Enter', false, false),
        ).toBeNull();
    });
});
