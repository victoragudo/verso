import { t } from '@/lib/i18n';
import {
    hexToHsl,
    hslToHex,
    hueDistance,
    rgbDistance,
    rgbToHex,
} from './color';
import type { Rgb } from './color';
import { makeReadable, reviewPalette } from './contrast';
import type { ColorRole, Palette } from './contrast';

// Turns the pixels of a logo into its main colors, then into site palettes that are easy to read.

export type LogoColor = { hex: string; share: number };

export type PaletteChoice = {
    key: 'light' | 'dark' | 'soft' | 'complementary';
    label: string;
    description: string;
    colors: Record<ColorRole, string>;
};

// Pixels that are mostly see-through are skipped.
const MIN_ALPHA = 128;

// Bits kept per channel when grouping pixels: 4 bits gives 4096 groups.
const QUANTIZE_SHIFT = 4;

// Groups closer than this (RGB distance) count as one color.
const MERGE_DISTANCE = 48;

// A logo color needs at least this share of the pixels to become the accent.
const MIN_ACCENT_SHARE = 0.02;

// Hues closer than this are the same color family.
const SAME_HUE = 40;

// Used when the logo has no color at all.
const FALLBACK_ACCENT = '#2e5e4e';

type Bucket = { r: number; g: number; b: number; count: number };

// The main colors of an image, most used first. pixels is RGBA, like ImageData.data.
export function extractColors(
    pixels: ArrayLike<number>,
    maxColors = 6,
): LogoColor[] {
    const buckets = new Map<number, Bucket>();
    let total = 0;

    for (let index = 0; index + 3 < pixels.length; index += 4) {
        if (pixels[index + 3] < MIN_ALPHA) {
            continue;
        }

        const r = pixels[index];
        const g = pixels[index + 1];
        const b = pixels[index + 2];
        const key =
            ((r >> QUANTIZE_SHIFT) << 8) |
            ((g >> QUANTIZE_SHIFT) << 4) |
            (b >> QUANTIZE_SHIFT);
        const bucket = buckets.get(key) ?? { r: 0, g: 0, b: 0, count: 0 };

        bucket.r += r;
        bucket.g += g;
        bucket.b += b;
        bucket.count += 1;
        buckets.set(key, bucket);
        total += 1;
    }

    if (total === 0) {
        return [];
    }

    const groups: { rgb: Rgb; count: number }[] = [];
    const sorted = [...buckets.values()].sort((a, b) => b.count - a.count);

    for (const bucket of sorted) {
        const rgb: Rgb = [
            bucket.r / bucket.count,
            bucket.g / bucket.count,
            bucket.b / bucket.count,
        ];
        const near = groups.find(
            (group) => rgbDistance(group.rgb, rgb) < MERGE_DISTANCE,
        );

        if (near) {
            const weight = near.count + bucket.count;
            near.rgb = [0, 1, 2].map(
                (channel) =>
                    (near.rgb[channel] * near.count +
                        rgb[channel] * bucket.count) /
                    weight,
            ) as Rgb;
            near.count = weight;
        } else {
            groups.push({ rgb, count: bucket.count });
        }
    }

    return groups
        .sort((a, b) => b.count - a.count)
        .slice(0, Math.max(1, maxColors))
        .map((group) => ({
            hex: rgbToHex(group.rgb),
            share: group.count / total,
        }));
}

// How much a color stands out: saturated, not too light, not too dark.
function vividness(hex: string): number {
    const { s, l } = hexToHsl(hex);

    return s * (1 - Math.abs(l - 0.5) * 1.6);
}

// The logo color that makes the best accent: the most vivid one with enough pixels, else the darkest non white one.
export function pickAccent(colors: LogoColor[]): string {
    const candidates = colors.filter(
        (color) => color.share >= MIN_ACCENT_SHARE,
    );
    const vivid = [...candidates]
        .filter((color) => vividness(color.hex) > 0.15)
        .sort(
            (a, b) =>
                vividness(b.hex) * Math.sqrt(b.share) -
                vividness(a.hex) * Math.sqrt(a.share),
        )[0];

    if (vivid) {
        return vivid.hex;
    }

    const dark = [...candidates]
        .filter((color) => hexToHsl(color.hex).l < 0.85)
        .sort((a, b) => hexToHsl(a.hex).l - hexToHsl(b.hex).l)[0];

    return dark?.hex ?? FALLBACK_ACCENT;
}

// A second logo color from another color family, for the two color palette. Null when the logo has one family.
export function pickSecondColor(
    colors: LogoColor[],
    accent: string,
): string | null {
    const accentHue = hexToHsl(accent).h;

    return (
        colors.find((color) => {
            const hsl = hexToHsl(color.hex);

            return (
                color.share >= MIN_ACCENT_SHARE &&
                vividness(color.hex) > 0.15 &&
                hueDistance(hsl.h, accentHue) > SAME_HUE
            );
        })?.hex ?? null
    );
}

// Four palettes from one accent: light (the main one), dark, soft and two colors. Every palette is made easy to read.
export function buildPalettes(
    accent: string,
    second: string | null = null,
): PaletteChoice[] {
    const { h, s, l } = hexToHsl(accent);
    const tint = (sat: number, light: number, hue = h) =>
        hslToHex({ h: hue, s: Math.min(s, sat), l: light });
    const otherHue = second ? hexToHsl(second).h : h + 180;
    const otherAccent =
        second ??
        hslToHex({ h: otherHue, s: Math.max(s, 0.45), l: Math.min(l, 0.4) });

    const choices: PaletteChoice[] = [
        {
            key: 'light',
            label: t('From your logo'),
            description: t(
                'A light page with your logo color for links and buttons.',
            ),
            colors: {
                accent,
                background: tint(0.25, 0.98),
                surface: tint(0.2, 0.94),
                text: tint(0.25, 0.12),
                muted: tint(0.12, 0.38),
            },
        },
        {
            key: 'dark',
            label: t('Dark'),
            description: t('Light text on a deep background. Calm at night.'),
            colors: {
                accent: hslToHex({ h, s, l: Math.max(l, 0.62) }),
                background: tint(0.3, 0.1),
                surface: tint(0.25, 0.16),
                text: tint(0.15, 0.93),
                muted: tint(0.12, 0.72),
            },
        },
        {
            key: 'soft',
            label: t('Soft'),
            description: t('A gentle tint of your color behind everything.'),
            colors: {
                accent: hslToHex({ h, s: s * 0.8, l: Math.min(l, 0.4) }),
                background: tint(0.45, 0.95),
                surface: tint(0.4, 0.89),
                text: tint(0.3, 0.16),
                muted: tint(0.2, 0.34),
            },
        },
        {
            key: 'complementary',
            label: t('Two colors'),
            description: t('Your logo color plus a second color for panels.'),
            colors: {
                accent,
                background: tint(0.08, 0.99),
                surface: hslToHex({
                    h: otherHue,
                    s: Math.min(Math.max(s, 0.3), 0.5),
                    l: 0.93,
                }),
                text: tint(0.1, 0.13),
                muted: hslToHex({
                    h: hexToHsl(otherAccent).h,
                    s: 0.25,
                    l: 0.35,
                }),
            },
        },
    ];

    return choices.map((choice) => ({
        ...choice,
        colors: makeReadable(choice.colors) as Record<ColorRole, string>,
    }));
}

// Palettes for the main colors of a logo, easy to read ones first.
export function palettesFromColors(colors: LogoColor[]): PaletteChoice[] {
    const accent = pickAccent(colors);

    return buildPalettes(accent, pickSecondColor(colors, accent)).sort(
        (a, b) =>
            reviewPalette(a.colors).length - reviewPalette(b.colors).length,
    );
}

// Only the roles a theme declares.
export function pickRoles(
    colors: Palette,
    roles: readonly ColorRole[],
): Palette {
    return Object.fromEntries(
        roles.flatMap((role) => (colors[role] ? [[role, colors[role]]] : [])),
    );
}
