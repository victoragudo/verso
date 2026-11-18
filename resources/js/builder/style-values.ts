// Checks for the style values that are more than one word: gradients, background pictures and focal points.
// The same rules as App\Verso\Editor\Styles\StyleValues in PHP; tests/Fixtures/block-styles.json keeps them equal.

export const MAX_GRADIENT_STOPS = 5;
export const Z_INDEX_MIN = -10;
export const Z_INDEX_MAX = 10000;

const TOKEN = /^var:([a-z][a-z0-9]*)\.([a-z0-9][a-z0-9-]{0,40})$/;
const HEX = /^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i;
const GRADIENT =
    /^(linear|radial)-gradient\(\s*([0-9]{1,3}deg|circle|ellipse)\s*,(.+)\)$/i;
const STOP =
    /^\s*(#[0-9a-f]{3,8}|var:[a-z][a-z0-9]*\.[a-z0-9][a-z0-9-]{0,40})(?:\s+([0-9]{1,3})%)?\s*$/i;
const IMAGE =
    /^(https?:\/\/[a-z0-9.-]+(:[0-9]{1,5})?)?\/[A-Za-z0-9/_.~%-]{1,500}(\?[A-Za-z0-9=&_.%-]{0,300})?$/i;
const FOCAL = /^([0-9]{1,3})%\s+([0-9]{1,3})%$/;

function color(value: string): string | null {
    const token = TOKEN.exec(value);

    if (token) {
        return `var(--verso-${token[1]}-${token[2]})`;
    }

    return HEX.test(value) ? value.toLowerCase() : null;
}

// The CSS of a gradient like "linear-gradient(135deg, #1f6f5c 0%, var:color.accent 100%)", or null.
export function gradientCss(value: unknown): string | null {
    if (typeof value !== 'string' || value.length > 400) {
        return null;
    }

    const match = GRADIENT.exec(value.trim());

    if (!match) {
        return null;
    }

    const kind = match[1].toLowerCase();
    const shape = match[2].toLowerCase();

    if (
        (kind === 'linear') !== shape.endsWith('deg') ||
        (kind === 'linear' && parseInt(shape, 10) > 360)
    ) {
        return null;
    }

    const stops = match[3].split(',');

    if (stops.length < 2 || stops.length > MAX_GRADIENT_STOPS) {
        return null;
    }

    const css: string[] = [];

    for (const stop of stops) {
        const part = STOP.exec(stop);
        const value = part ? color(part[1]) : null;

        if (!part || value === null || (part[2] && Number(part[2]) > 100)) {
            return null;
        }

        css.push(part[2] ? `${value} ${Number(part[2])}%` : value);
    }

    return `${kind}-gradient(${shape}, ${css.join(', ')})`;
}

// The CSS of a background picture address, or null when the address is not safe.
export function imageCss(value: unknown): string | null {
    return typeof value === 'string' && IMAGE.test(value)
        ? `url("${value}")`
        : null;
}

// The CSS of a focal point like "30% 70%", or null.
export function focalCss(value: unknown): string | null {
    if (typeof value !== 'string') {
        return null;
    }

    const match = FOCAL.exec(value.trim());

    if (!match || Number(match[1]) > 100 || Number(match[2]) > 100) {
        return null;
    }

    return `${Number(match[1])}% ${Number(match[2])}%`;
}

// A whole number for z-index, or null.
export function zIndexCss(value: unknown): string | null {
    return typeof value === 'number' &&
        Number.isInteger(value) &&
        value >= Z_INDEX_MIN &&
        value <= Z_INDEX_MAX
        ? String(value)
        : null;
}

// One color stop of a gradient the Styles panel builds.
export type GradientStop = { color: string; at: number };

export type GradientParts = {
    kind: 'linear' | 'radial';
    angle: number;
    stops: GradientStop[];
};

// The stored text of a gradient made in the Styles panel.
export function gradientText(parts: GradientParts): string {
    const head = parts.kind === 'linear' ? `${parts.angle}deg` : 'circle';
    const stops = parts.stops.map((stop) => `${stop.color} ${stop.at}%`);

    return `${parts.kind}-gradient(${head}, ${stops.join(', ')})`;
}

// The parts of a stored gradient, so the Styles panel can show them. Null for values it can not read.
export function gradientParts(value: unknown): GradientParts | null {
    if (gradientCss(value) === null) {
        return null;
    }

    const match = GRADIENT.exec((value as string).trim()) as RegExpExecArray;
    const kind = match[1].toLowerCase() as GradientParts['kind'];
    const stops = match[3].split(',').map((stop, index, all) => {
        const part = STOP.exec(stop) as RegExpExecArray;

        return {
            color: part[1],
            at: part[2]
                ? Number(part[2])
                : Math.round((index / Math.max(all.length - 1, 1)) * 100),
        };
    });

    return {
        kind,
        angle: kind === 'linear' ? parseInt(match[2], 10) : 180,
        stops,
    };
}
