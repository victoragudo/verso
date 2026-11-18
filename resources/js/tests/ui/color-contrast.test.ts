import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

// Checks that the admin color tokens meet WCAG 2.2 AA text contrast in light and dark mode.

const css = readFileSync(
    resolve(process.cwd(), 'resources/css/app.css'),
    'utf8',
);

type Rgb = [number, number, number];

// Reads the raw light or dark value of every token from the first :root block
// (--name-light and --name-dark); tokens with one value are the same in both.
function tokensFor(scheme: 'light' | 'dark'): Record<string, string> {
    const start = css.indexOf(':root {');
    const body = css.slice(start, css.indexOf('\n}', start));
    const tokens: Record<string, string> = {};

    for (const match of body.matchAll(/--([\w-]+?)(-light|-dark)?:\s*(oklch\([^)]+\))/g)) {
        const [, name, suffix, value] = match;

        if (!suffix || suffix === `-${scheme}`) {
            tokens[name] = value;
        }
    }

    return tokens;
}

// Turns an oklch() value into linear sRGB channels (0 to 1).
function oklchToLinearRgb(value: string): Rgb {
    const [l, c, h] = value
        .replace(/oklch\(|\)/g, '')
        .trim()
        .split(/\s+/)
        .map(Number);
    const hue = ((h || 0) * Math.PI) / 180;
    const a = c * Math.cos(hue);
    const b = c * Math.sin(hue);

    const l_ = (l + 0.3963377774 * a + 0.2158037573 * b) ** 3;
    const m_ = (l - 0.1055613458 * a - 0.0638541728 * b) ** 3;
    const s_ = (l - 0.0894841775 * a - 1.291485548 * b) ** 3;

    const clamp = (channel: number) => Math.min(1, Math.max(0, channel));

    return [
        clamp(4.0767416621 * l_ - 3.3077115913 * m_ + 0.2309699292 * s_),
        clamp(-1.2684380046 * l_ + 2.6097574011 * m_ - 0.3413193965 * s_),
        clamp(-0.0041960863 * l_ - 0.7034186147 * m_ + 1.707614701 * s_),
    ];
}

function luminance(value: string): number {
    const [r, g, b] = oklchToLinearRgb(value);

    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

function contrast(foreground: string, background: string): number {
    const first = luminance(foreground);
    const second = luminance(background);

    return (Math.max(first, second) + 0.05) / (Math.min(first, second) + 0.05);
}

const pairs: Array<[string, string]> = [
    ['muted-foreground', 'background'],
    ['muted-foreground', 'card'],
    ['muted-foreground', 'muted'],
    ['destructive-foreground', 'card'],
    ['destructive-foreground', 'muted'],
    ['danger-text', 'danger-soft'],
    ['foreground', 'background'],
];

describe.each(['light', 'dark'] as const)('%s theme tokens', (scheme) => {
    const tokens = tokensFor(scheme);

    it.each(pairs)('%s on %s reaches 4.5:1', (foreground, background) => {
        expect(tokens[foreground]).toBeDefined();
        expect(tokens[background]).toBeDefined();
        expect(
            contrast(tokens[foreground], tokens[background]),
        ).toBeGreaterThanOrEqual(4.5);
    });
});

describe('contrast helper', () => {
    it('gives 21:1 for black on white', () => {
        expect(contrast('oklch(0 0 0)', 'oklch(1 0 0)')).toBeCloseTo(21, 0);
    });

    it('gives 1:1 for the same color', () => {
        expect(
            contrast(
                'oklch(0.552 0.016 285.938)',
                'oklch(0.552 0.016 285.938)',
            ),
        ).toBeCloseTo(1, 5);
    });
});
