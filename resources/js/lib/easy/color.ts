// Small color helpers for hex colors: parse, print, mix and move between RGB and HSL.

export type Rgb = [number, number, number];

export type Hsl = { h: number; s: number; l: number };

const HEX = /^#([0-9a-f]{6}|[0-9a-f]{3})$/i;

// True for "#2e5e4e" and "#fff".
export function isHexColor(value: string): boolean {
    return HEX.test(value.trim());
}

// "#FFF" becomes "#ffffff". Returns null for anything that is not a hex color.
export function normalizeHex(value: string): string | null {
    const trimmed = value.trim().toLowerCase();

    if (!HEX.test(trimmed)) {
        return null;
    }

    if (trimmed.length === 4) {
        return `#${trimmed
            .slice(1)
            .split('')
            .map((digit) => digit + digit)
            .join('')}`;
    }

    return trimmed;
}

export function hexToRgb(hex: string): Rgb {
    const value = normalizeHex(hex) ?? '#000000';

    return [
        parseInt(value.slice(1, 3), 16),
        parseInt(value.slice(3, 5), 16),
        parseInt(value.slice(5, 7), 16),
    ];
}

export function rgbToHex(rgb: Rgb): string {
    return `#${rgb
        .map((channel) =>
            Math.max(0, Math.min(255, Math.round(channel)))
                .toString(16)
                .padStart(2, '0'),
        )
        .join('')}`;
}

// The color moved part of the way to another one, channel by channel (0 keeps it, 1 reaches the other).
export function mixRgb(from: Rgb, to: Rgb, amount: number): Rgb {
    return [0, 1, 2].map((index) =>
        Math.round(from[index] + (to[index] - from[index]) * amount),
    ) as Rgb;
}

export function rgbToHsl([red, green, blue]: Rgb): Hsl {
    const r = red / 255;
    const g = green / 255;
    const b = blue / 255;
    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    const l = (max + min) / 2;

    if (max === min) {
        return { h: 0, s: 0, l };
    }

    const d = max - min;
    const s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
    let h: number;

    if (max === r) {
        h = (g - b) / d + (g < b ? 6 : 0);
    } else if (max === g) {
        h = (b - r) / d + 2;
    } else {
        h = (r - g) / d + 4;
    }

    return { h: h * 60, s, l };
}

export function hslToRgb({ h, s, l }: Hsl): Rgb {
    const hue = (((h % 360) + 360) % 360) / 360;
    const sat = Math.max(0, Math.min(1, s));
    const light = Math.max(0, Math.min(1, l));

    if (sat === 0) {
        return [light * 255, light * 255, light * 255].map(Math.round) as Rgb;
    }

    const q = light < 0.5 ? light * (1 + sat) : light + sat - light * sat;
    const p = 2 * light - q;
    const channel = (offset: number) => {
        let t = hue + offset;

        if (t < 0) {
            t += 1;
        }

        if (t > 1) {
            t -= 1;
        }

        if (t < 1 / 6) {
            return p + (q - p) * 6 * t;
        }

        if (t < 1 / 2) {
            return q;
        }

        if (t < 2 / 3) {
            return p + (q - p) * (2 / 3 - t) * 6;
        }

        return p;
    };

    return [channel(1 / 3), channel(0), channel(-1 / 3)].map((value) =>
        Math.round(value * 255),
    ) as Rgb;
}

export function hexToHsl(hex: string): Hsl {
    return rgbToHsl(hexToRgb(hex));
}

export function hslToHex(hsl: Hsl): string {
    return rgbToHex(hslToRgb(hsl));
}

// Distance of two colors in RGB, from 0 (same) to about 441 (black and white).
export function rgbDistance(a: Rgb, b: Rgb): number {
    return Math.hypot(a[0] - b[0], a[1] - b[1], a[2] - b[2]);
}

// Smallest angle between two hues, from 0 to 180 degrees.
export function hueDistance(a: number, b: number): number {
    const difference = Math.abs(a - b) % 360;

    return difference > 180 ? 360 - difference : difference;
}
