import { describe, expect, it } from 'vitest';
import { hexToHsl, hueDistance, normalizeHex } from '@/lib/easy/color';
import { reviewPalette } from '@/lib/easy/contrast';
import {
    buildPalettes,
    extractColors,
    palettesFromColors,
    pickAccent,
    pickRoles,
    pickSecondColor,
} from '@/lib/easy/palette';

// Builds RGBA pixels from [hex, how many, alpha] rows, like a logo read through a canvas.
function pixels(rows: [string, number, number?][]): Uint8ClampedArray {
    const data: number[] = [];

    for (const [hex, count, alpha = 255] of rows) {
        const value = normalizeHex(hex)!;
        const rgb = [1, 3, 5].map((start) =>
            parseInt(value.slice(start, start + 2), 16),
        );

        for (let index = 0; index < count; index++) {
            data.push(rgb[0], rgb[1], rgb[2], alpha);
        }
    }

    return new Uint8ClampedArray(data);
}

// A bakery logo: lots of white, a teal mark, a little orange and a see-through border.
const bakeryLogo = pixels([
    ['#ffffff', 600],
    ['#0f766e', 250],
    ['#11786f', 50],
    ['#ea580c', 80],
    ['#000000', 400, 0],
]);

describe('extractColors', () => {
    it('groups close colors, skips see-through pixels and sorts by use', () => {
        const colors = extractColors(bakeryLogo, 6);

        expect(colors.map((color) => color.hex)).toEqual([
            '#ffffff',
            '#0f766e',
            '#ea580c',
        ]);
        expect(colors[0].share).toBeCloseTo(600 / 980, 3);
        expect(colors[1].share).toBeCloseTo(300 / 980, 3);
    });

    it('keeps at most the asked number of colors', () => {
        expect(extractColors(bakeryLogo, 2)).toHaveLength(2);
    });

    it('returns nothing for a fully see-through picture', () => {
        expect(extractColors(pixels([['#123456', 20, 0]]))).toEqual([]);
    });
});

describe('accent and second color', () => {
    it('picks the vivid logo color as accent, not the white background', () => {
        const colors = extractColors(bakeryLogo);

        expect(pickAccent(colors)).toBe('#0f766e');
        expect(pickSecondColor(colors, '#0f766e')).toBe('#ea580c');
    });

    it('uses the darkest color of a black and white logo', () => {
        const colors = extractColors(
            pixels([
                ['#ffffff', 500],
                ['#1c1917', 200],
            ]),
        );

        expect(pickAccent(colors)).toBe('#1c1917');
        expect(pickSecondColor(colors, '#1c1917')).toBeNull();
    });

    it('falls back to a calm green when there is no color at all', () => {
        expect(pickAccent([])).toBe('#2e5e4e');
    });
});

describe('palettes', () => {
    it('makes a main palette and three alternatives that are all easy to read', () => {
        const palettes = buildPalettes('#0f766e', '#ea580c');

        expect(palettes.map((palette) => palette.key)).toEqual([
            'light',
            'dark',
            'soft',
            'complementary',
        ]);

        for (const palette of palettes) {
            expect(reviewPalette(palette.colors)).toEqual([]);
            expect(Object.keys(palette.colors).sort()).toEqual([
                'accent',
                'background',
                'muted',
                'surface',
                'text',
            ]);
        }
    });

    it('keeps the logo color in the main palette and darkens the dark one background', () => {
        const [light, dark] = buildPalettes('#0f766e');

        expect(light.colors.accent).toBe('#0f766e');
        expect(hexToHsl(light.colors.background).l).toBeGreaterThan(0.9);
        expect(hexToHsl(dark.colors.background).l).toBeLessThan(0.2);
    });

    it('nudges a too light logo color until links stay visible', () => {
        const [light] = buildPalettes('#fde047');

        expect(light.colors.accent).not.toBe('#fde047');
        expect(reviewPalette(light.colors)).toEqual([]);
    });

    it('uses the second logo color for the two color palette, or the opposite hue', () => {
        const withSecond = buildPalettes('#0f766e', '#ea580c')[3];
        const without = buildPalettes('#0f766e')[3];

        expect(
            hueDistance(
                hexToHsl(withSecond.colors.surface).h,
                hexToHsl('#ea580c').h,
            ),
        ).toBeLessThan(15);
        expect(
            hueDistance(
                hexToHsl(without.colors.surface).h,
                hexToHsl('#0f766e').h,
            ),
        ).toBeGreaterThan(150);
    });

    it('builds palettes straight from logo colors', () => {
        const palettes = palettesFromColors(extractColors(bakeryLogo));

        expect(palettes).toHaveLength(4);
        expect(palettes[0].colors.accent).toBe('#0f766e');
    });

    it('keeps only the roles a theme declares', () => {
        expect(
            pickRoles(
                { accent: '#0f766e', muted: '#545f59', text: '#1b2420' },
                ['accent', 'text', 'surface'],
            ),
        ).toEqual({
            accent: '#0f766e',
            text: '#1b2420',
        });
    });
});
