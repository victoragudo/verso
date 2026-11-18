import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

const css = readFileSync('resources/css/app.css', 'utf8');

describe('admin accessibility CSS', () => {
    it('draws real outlines for focus and the current item in forced colors mode', () => {
        const block = css.slice(css.indexOf('@media (forced-colors: active)'));

        expect(block).toContain(':focus-visible');
        expect(block).toContain('outline: 2px solid Highlight');
        expect(block).toContain("[aria-current='page']");
        expect(block).toContain("[data-slot='status-badge']");
    });

    it('keeps dialog and sheet movement behind a reduced motion check', () => {
        const noPreference = css.indexOf(
            '@media (prefers-reduced-motion: no-preference)',
        );
        const scale = css.indexOf('scale: 0.95');

        expect(noPreference).toBeGreaterThan(-1);
        expect(scale).toBeGreaterThan(noPreference);
    });
});

describe('native select picker', () => {
    it('opens below the field first, like the Radix select', () => {
        const selectCss = readFileSync('resources/css/select.css', 'utf8');

        expect(selectCss).toContain('position-try-order: normal');
    });
});
