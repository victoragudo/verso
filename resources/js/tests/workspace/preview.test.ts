import { describe, expect, it } from 'vitest';
import { previewFit } from '@/components/verso/workspace/preview-scale';
import {
    defaultPreviewTarget,
    previewTargetFor,
    targetUsesLayout,
} from '@/components/verso/workspace/preview-target';
import type { PreviewTarget } from '@/components/verso/workspace/preview-target';

const targets: PreviewTarget[] = [
    { value: 'home', label: 'Home page', usesLayout: false },
    {
        value: 'entry:12',
        label: 'A Slow Weekend in Porto (Post)',
        usesLayout: true,
    },
    { value: 'entry:40', label: 'About us (Page)', usesLayout: false },
    { value: 'search', label: 'Search results', usesLayout: false },
];

describe('preview target', () => {
    it('opens on the first page that uses the layout, not the home page', () => {
        expect(defaultPreviewTarget(targets)).toBe('entry:12');
    });

    it('falls back to the first page when no page uses the layout', () => {
        expect(
            defaultPreviewTarget(
                targets.map((target) => ({ ...target, usesLayout: false })),
            ),
        ).toBe('home');
        expect(defaultPreviewTarget([])).toBe('home');
    });

    it('keeps the page people picked while it is still offered', () => {
        expect(previewTargetFor(targets, 'search')).toBe('search');
        expect(previewTargetFor(targets, 'entry:99')).toBe('entry:12');
        expect(previewTargetFor(targets, null)).toBe('entry:12');
    });

    it('says whether the shown page uses the layout', () => {
        expect(targetUsesLayout(targets, 'home')).toBe(false);
        expect(targetUsesLayout(targets, 'entry:12')).toBe(true);
        expect(
            targetUsesLayout([{ value: 'home', label: 'Home page' }], 'home'),
        ).toBeNull();
    });
});

describe('preview fit', () => {
    it('fills a wide pane with the computer page at full size', () => {
        expect(
            previewFit({
                paneWidth: 1700,
                deviceWidth: null,
                desktopMinWidth: 1280,
            }),
        ).toEqual({
            frameWidth: 1700,
            scale: 1,
        });
    });

    it('shrinks the computer page in a narrow pane', () => {
        const fit = previewFit({
            paneWidth: 960,
            deviceWidth: null,
            desktopMinWidth: 1280,
        });

        expect(fit.frameWidth).toBe(1280);
        expect(fit.scale).toBeCloseTo(0.75);
    });

    it('draws a phone at its own width without growing it', () => {
        expect(
            previewFit({
                paneWidth: 900,
                deviceWidth: 390,
                desktopMinWidth: 1280,
            }),
        ).toEqual({
            frameWidth: 390,
            scale: 1,
        });
        expect(
            previewFit({
                paneWidth: 384,
                deviceWidth: 768,
                desktopMinWidth: 1280,
            }).scale,
        ).toBeCloseTo(0.5);
    });
});
