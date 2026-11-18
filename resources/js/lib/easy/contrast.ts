import { msg, t } from '@/lib/i18n';
import { hexToRgb, isHexColor, mixRgb, rgbToHex } from './color';

// WCAG contrast checks for a site palette. App\Verso\Easy\Styles\ContrastChecker does the same on the server,
// and tests/Fixtures/contrast.json keeps the two in line.

export type ColorRole = 'accent' | 'background' | 'text' | 'muted' | 'surface';

export type Palette = Partial<Record<ColorRole, string>>;

export type ContrastWarning = {
    key: string;
    foreground: ColorRole;
    background: ColorRole;
    ratio: number;
    minimum: number;
    fix: ColorRole;
    direction: 'darker' | 'lighter';
    message: string;
};

// WCAG AA: normal text needs 4.5:1, large text and buttons need 3:1.
export const BODY_TEXT = 4.5;
export const LARGE_TEXT = 3;

// Share of the way to black or white that one "Fix it" step moves a color.
const FIX_STEP = 0.02;

type Check = {
    key: string;
    foreground: ColorRole;
    background: ColorRole;
    minimum: number;
    fix: ColorRole;
    message: string;
};

// Pairs to check, in order. Each message picks its words by direction (darker or lighter).
export const CONTRAST_CHECKS: Check[] = [
    {
        key: 'text-background',
        foreground: 'text',
        background: 'background',
        minimum: BODY_TEXT,
        fix: 'text',
        message: msg(
            '{direction, select, darker {The text is hard to read on this background. Try a darker text color.} other {The text is hard to read on this background. Try a lighter text color.}}',
        ),
    },
    {
        key: 'muted-background',
        foreground: 'muted',
        background: 'background',
        minimum: BODY_TEXT,
        fix: 'muted',
        message: msg(
            '{direction, select, darker {Quiet text, like dates and captions, is hard to read on this background. Try a darker quiet text color.} other {Quiet text, like dates and captions, is hard to read on this background. Try a lighter quiet text color.}}',
        ),
    },
    {
        key: 'accent-background',
        foreground: 'accent',
        background: 'background',
        minimum: LARGE_TEXT,
        fix: 'accent',
        message: msg(
            '{direction, select, darker {Links and buttons are hard to see on this background. Try a darker accent color.} other {Links and buttons are hard to see on this background. Try a lighter accent color.}}',
        ),
    },
    {
        key: 'text-surface',
        foreground: 'text',
        background: 'surface',
        minimum: BODY_TEXT,
        fix: 'surface',
        message: msg(
            '{direction, select, darker {Text on panels is hard to read. Try a darker panel color.} other {Text on panels is hard to read. Try a lighter panel color.}}',
        ),
    },
];

// WCAG relative luminance of a hex color, from 0 (black) to 1 (white).
export function luminance(hex: string): number {
    const [red, green, blue] = hexToRgb(hex).map((channel) => {
        const value = channel / 255;

        return value <= 0.03928
            ? value / 12.92
            : ((value + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * red + 0.7152 * green + 0.0722 * blue;
}

// Contrast ratio of two colors, from 1 (same) to 21 (black on white).
export function contrastRatio(first: string, second: string): number {
    const a = luminance(first);
    const b = luminance(second);

    return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
}

export function passes(
    foreground: string,
    background: string,
    minimum = BODY_TEXT,
): boolean {
    return contrastRatio(foreground, background) >= minimum;
}

// "darker" when black stands out more on the other color than white does, else "lighter".
export function fixDirection(other: string): 'darker' | 'lighter' {
    return contrastRatio('#000000', other) >= contrastRatio('#ffffff', other)
        ? 'darker'
        : 'lighter';
}

// The color moved toward black or white, step by step, until it reaches the minimum ratio against the other color.
export function fixColor(
    color: string,
    other: string,
    minimum: number,
): string {
    const target: [number, number, number] =
        fixDirection(other) === 'darker' ? [0, 0, 0] : [255, 255, 255];
    const start = hexToRgb(color);
    const steps = Math.round(1 / FIX_STEP);

    for (let step = 0; step <= steps; step++) {
        const mixed = rgbToHex(mixRgb(start, target, step * FIX_STEP));

        if (contrastRatio(mixed, other) >= minimum) {
            return mixed;
        }
    }

    return rgbToHex(target);
}

// Warnings for the pairs of a palette that are hard to read. A color already flagged once is not flagged again.
export function reviewPalette(palette: Palette): ContrastWarning[] {
    const warnings: ContrastWarning[] = [];

    for (const check of CONTRAST_CHECKS) {
        const foreground = palette[check.foreground];
        const background = palette[check.background];

        if (
            !foreground ||
            !background ||
            !isHexColor(foreground) ||
            !isHexColor(background)
        ) {
            continue;
        }

        const ratio = contrastRatio(foreground, background);

        if (
            ratio >= check.minimum ||
            warnings.some((warning) => warning.foreground === check.foreground)
        ) {
            continue;
        }

        const other = check.fix === check.foreground ? background : foreground;
        const direction = fixDirection(other);

        warnings.push({
            key: check.key,
            foreground: check.foreground,
            background: check.background,
            ratio: Math.round(ratio * 100) / 100,
            minimum: check.minimum,
            fix: check.fix,
            direction,
            message: t(check.message, { direction }),
        });
    }

    return warnings;
}

// The palette with the color of one warning nudged until the pair is easy to read.
// A text color moved this way also keeps going until it reads well on its other backgrounds, like panels.
export function fixWarning(
    palette: Palette,
    warning: ContrastWarning,
): Palette {
    const color = palette[warning.fix];
    const otherRole =
        warning.fix === warning.foreground
            ? warning.background
            : warning.foreground;
    const other = palette[otherRole];

    if (!color || !other) {
        return palette;
    }

    const fixed: Palette = {
        ...palette,
        [warning.fix]: fixColor(color, other, warning.minimum),
    };

    if (warning.fix !== warning.foreground) {
        return fixed;
    }

    for (const check of CONTRAST_CHECKS) {
        const foreground = fixed[warning.fix];
        const background = fixed[check.background];

        if (
            check.foreground !== warning.fix ||
            check.key === warning.key ||
            !foreground ||
            !background ||
            passes(foreground, background, check.minimum) ||
            fixDirection(background) !== warning.direction
        ) {
            continue;
        }

        fixed[warning.fix] = fixColor(foreground, background, check.minimum);
    }

    return fixed;
}

// The palette with every warning fixed, one after the other.
export function makeReadable(palette: Palette): Palette {
    let fixed = palette;

    for (let round = 0; round < CONTRAST_CHECKS.length * 2; round++) {
        const [warning] = reviewPalette(fixed);

        if (!warning) {
            break;
        }

        fixed = fixWarning(fixed, warning);
    }

    return fixed;
}
