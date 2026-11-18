import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import {
    BODY_TEXT,
    CONTRAST_CHECKS,
    LARGE_TEXT,
    contrastRatio,
    fixColor,
    fixWarning,
    makeReadable,
    passes,
    reviewPalette,
} from '@/lib/easy/contrast';
import type { Palette } from '@/lib/easy/contrast';

type Fixture = {
    minimums: { body: number; large: number };
    checks: string[];
    ratios: { foreground: string; background: string; ratio: number }[];
    reviews: {
        name: string;
        colors: Palette;
        warnings: {
            key: string;
            fix: string;
            direction: string;
            message: string;
        }[];
    }[];
    fixes: {
        color: string;
        other: string;
        minimum: number;
        expected: string;
    }[];
};

// The same cases the PHP ContrastChecker test reads, so the page and the server warn alike.
const fixture = JSON.parse(
    readFileSync(
        resolve(process.cwd(), 'tests/Fixtures/contrast.json'),
        'utf8',
    ),
) as Fixture;

describe('contrast helpers', () => {
    it('uses the shared minimums and checks', () => {
        expect(fixture.minimums).toEqual({
            body: BODY_TEXT,
            large: LARGE_TEXT,
        });
        expect(CONTRAST_CHECKS.map((check) => check.key)).toEqual(
            fixture.checks,
        );
    });

    it.each(fixture.ratios)(
        'measures $foreground on $background',
        ({ foreground, background, ratio }) => {
            expect(
                Math.round(contrastRatio(foreground, background) * 100) / 100,
            ).toBe(ratio);
        },
    );

    it.each(fixture.reviews)(
        'reviews the palette "$name"',
        ({ colors, warnings }) => {
            expect(
                reviewPalette(colors).map(
                    ({ key, fix, direction, message }) => ({
                        key,
                        fix,
                        direction,
                        message,
                    }),
                ),
            ).toEqual(warnings);
        },
    );

    it.each(fixture.fixes)(
        'fixes $color against $other',
        ({ color, other, minimum, expected }) => {
            const fixed = fixColor(color, other, minimum);

            expect(fixed).toBe(expected);
            expect(contrastRatio(fixed, other)).toBeGreaterThanOrEqual(minimum);
        },
    );

    it('knows easy and hard pairs', () => {
        expect(passes('#000000', '#ffffff')).toBe(true);
        expect(passes('#c9c6bf', '#ffffff')).toBe(false);
        expect(passes('#949494', '#ffffff', LARGE_TEXT)).toBe(true);
        expect(
            reviewPalette({ text: 'tomato', background: '#ffffff' }),
        ).toEqual([]);
    });

    it('keeps a fixed text color readable on panels too', () => {
        const palette: Palette = {
            background: '#f6f7f3',
            text: '#c9c6bf',
            surface: '#eceee8',
        };
        const fixed = fixWarning(palette, reviewPalette(palette)[0]);

        expect(fixed.surface).toBe('#eceee8');
        expect(reviewPalette(fixed)).toEqual([]);
    });

    it('fixes one warning at a time and every warning at once', () => {
        const palette: Palette = {
            accent: '#facc15',
            background: '#ffffff',
            text: '#b8b5ad',
            muted: '#c9c6bf',
            surface: '#f3f4f6',
        };
        const [first] = reviewPalette(palette);
        const once = fixWarning(palette, first);

        expect(first.key).toBe('text-background');
        expect(passes(once.text!, once.background!)).toBe(true);
        expect(once.muted).toBe('#c9c6bf');
        expect(reviewPalette(makeReadable(palette))).toEqual([]);
    });
});
