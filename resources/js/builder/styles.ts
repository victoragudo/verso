import { cssValueRule, isValidCssValue } from '@/lib/css-value';
import type { CssValueRule } from '@/lib/css-value';
import type { Block } from '@/types';
import { focalCss, gradientCss, imageCss, zIndexCss } from './style-values';

// Attr that holds the style options of a block (same as BlockStyleSchema::ATTR in PHP).
export const DESIGN_ATTR = 'design';

// Attr that names the style preset of a block (same as BlockStyleSchema::PRESET_ATTR in PHP).
export const PRESET_ATTR = 'preset';

export type StyleValue = string | number | boolean;
export type Design = Record<string, unknown>;

// The same safe value checks as BlockStyleSchema in PHP, so the editor never saves a value the server refuses.
const TOKEN = /^var:([a-z][a-z0-9]*)\.([a-z0-9][a-z0-9-]{0,40})$/;
const HEX = /^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i;
const TOKEN_KINDS = [
    'length',
    'angle',
    'time',
    'color',
    'overlay',
    'number',
    'token',
    'gradient',
];

// Kinds checked by a value rule: units, keywords and plain numbers (same as StyleProperty::VALUE_KINDS).
export const VALUE_KINDS = ['length', 'angle', 'time'];

// The value rule of a size kind. Without one from the server, any value of the kind is allowed.
export function styleValueRule(
    kind: string,
    rule?: CssValueRule | null,
): CssValueRule {
    return rule ?? cssValueRule({ type: kind as CssValueRule['type'] });
}

// Fixed words of the keyword kinds: stored word => CSS word (same as BlockStyleSchema::KEYWORDS).
export const STYLE_KEYWORD_CSS: Record<string, Record<string, string>> = {
    display: { block: 'block', flex: 'flex', grid: 'grid' },
    direction: {
        row: 'row',
        column: 'column',
        'row-reverse': 'row-reverse',
        'column-reverse': 'column-reverse',
    },
    wrap: { wrap: 'wrap', nowrap: 'nowrap' },
    align: {
        start: 'start',
        center: 'center',
        end: 'end',
        stretch: 'stretch',
        baseline: 'baseline',
    },
    justify: {
        start: 'start',
        center: 'center',
        end: 'end',
        between: 'space-between',
        around: 'space-around',
        evenly: 'space-evenly',
    },
    transform: {
        none: 'none',
        uppercase: 'uppercase',
        lowercase: 'lowercase',
        capitalize: 'capitalize',
    },
    borderStyle: {
        none: 'none',
        solid: 'solid',
        dashed: 'dashed',
        dotted: 'dotted',
    },
    bgSize: { cover: 'cover', contain: 'contain', auto: 'auto' },
    bgRepeat: {
        'no-repeat': 'no-repeat',
        repeat: 'repeat',
        'repeat-x': 'repeat-x',
        'repeat-y': 'repeat-y',
    },
    bgAttachment: { scroll: 'scroll', fixed: 'fixed' },
};

// Fixed words of the keyword kinds.
export const STYLE_KEYWORDS: Record<string, string[]> = Object.fromEntries(
    Object.entries(STYLE_KEYWORD_CSS).map(([kind, words]) => [
        kind,
        Object.keys(words),
    ]),
);

export function isValidStyleValue(
    kind: string,
    value: unknown,
    rule?: CssValueRule | null,
): boolean {
    if (
        TOKEN_KINDS.includes(kind) &&
        typeof value === 'string' &&
        TOKEN.test(value)
    ) {
        return true;
    }

    switch (kind) {
        case 'length':
        case 'angle':
        case 'time':
            return isValidCssValue(value, styleValueRule(kind, rule));
        case 'color':
        case 'overlay':
            return typeof value === 'string' && HEX.test(value);
        case 'number':
            return typeof value === 'number' && value > 0 && value <= 4;
        case 'weight':
            return (
                Number.isInteger(value) &&
                (value as number) >= 100 &&
                (value as number) <= 900 &&
                (value as number) % 100 === 0
            );
        case 'columns':
            return (
                Number.isInteger(value) &&
                (value as number) >= 1 &&
                (value as number) <= 12
            );
        case 'sticky':
        case 'hidden':
            return typeof value === 'boolean';
        case 'gradient':
            return gradientCss(value) !== null;
        case 'image':
            return imageCss(value) !== null;
        case 'focal':
            return focalCss(value) !== null;
        case 'zIndex':
            return zIndexCss(value) !== null;
        case 'token':
            return false;
        default:
            return (
                typeof value === 'string' &&
                (STYLE_KEYWORDS[kind] ?? []).includes(value)
            );
    }
}

// Turns what people type into the stored value: numbers for number kinds, trimmed text for the rest.
export function parseStyleInput(kind: string, raw: string): StyleValue {
    const text = raw.trim();

    if (
        ['number', 'weight', 'columns', 'zIndex'].includes(kind) &&
        text !== ''
    ) {
        const number = Number(text);

        return Number.isNaN(number) || TOKEN.test(text) ? text : number;
    }

    return text;
}

function read(design: Design, path: string): unknown {
    return path
        .split('.')
        .reduce<unknown>(
            (value, key) =>
                value && typeof value === 'object'
                    ? (value as Record<string, unknown>)[key]
                    : undefined,
            design,
        );
}

// Plain values count as the "base" (every screen) value.
function isResponsive(value: unknown): value is Record<string, StyleValue> {
    return typeof value === 'object' && value !== null;
}

// The value for one screen size. Falls back to the bigger sizes, like the CSS does.
export function styleValue(
    design: Design,
    path: string,
    device: string,
    devices: string[] = ['base', 'tablet', 'mobile'],
): { value: StyleValue | undefined; inherited: boolean } {
    const raw = read(design, path);

    if (!isResponsive(raw)) {
        return {
            value: raw as StyleValue | undefined,
            inherited: device !== 'base' && raw !== undefined,
        };
    }

    const start = devices.indexOf(device);

    for (let index = start; index >= 0; index--) {
        if (raw[devices[index]] !== undefined) {
            return { value: raw[devices[index]], inherited: index !== start };
        }
    }

    return { value: undefined, inherited: false };
}

// Removes empty objects left after clearing a value.
function prune(value: Record<string, unknown>): Record<string, unknown> {
    const result: Record<string, unknown> = {};

    Object.entries(value).forEach(([key, item]) => {
        if (isResponsive(item) && !Array.isArray(item)) {
            const inner = prune(item as Record<string, unknown>);

            if (Object.keys(inner).length > 0) {
                result[key] = inner;
            }
        } else if (item !== undefined) {
            result[key] = item;
        }
    });

    return result;
}

// A new design with one value set (or cleared with undefined) for one screen size.
export function setStyleValue(
    design: Design,
    path: string,
    device: string,
    value: StyleValue | undefined,
): Design {
    const keys = path.split('.');
    const current = read(design, path);
    let next: unknown;

    if (device === 'base' && !isResponsive(current)) {
        next = value;
    } else {
        const values: Record<string, StyleValue | undefined> = isResponsive(
            current,
        )
            ? { ...current }
            : current === undefined
              ? {}
              : { base: current as StyleValue };
        values[device] = value;
        const filled = Object.entries(values).filter(
            ([, item]) => item !== undefined,
        );
        next =
            filled.length === 1 && filled[0][0] === 'base'
                ? filled[0][1]
                : filled.length === 0
                  ? undefined
                  : Object.fromEntries(filled);
    }

    const copy = structuredClone(design) as Record<string, unknown>;
    let target = copy;

    keys.slice(0, -1).forEach((key) => {
        if (!isResponsive(target[key])) {
            target[key] = {};
        }

        target = target[key] as Record<string, unknown>;
    });
    target[keys[keys.length - 1]] = next;

    return prune(copy);
}

export function blockDesign(block: Block): Design {
    const design = block.attrs?.[DESIGN_ATTR];

    return isResponsive(design) ? (design as Design) : {};
}

// The block with a new design. An empty design is removed from the attrs.
export function withDesign(block: Block, design: Design): Block {
    const attrs = { ...block.attrs };

    if (Object.keys(design).length === 0) {
        delete attrs[DESIGN_ATTR];
    } else {
        attrs[DESIGN_ATTR] = design;
    }

    return { ...block, attrs };
}

// Styles copied with "Copy styles", kept until the page is closed.
let copiedDesign: Design | null = null;
const listeners = new Set<() => void>();

export const styleClipboard = {
    copy(design: Design) {
        copiedDesign = structuredClone(design);
        listeners.forEach((listener) => listener());
    },
    read: (): Design | null => copiedDesign,
    subscribe: (listener: () => void) => {
        listeners.add(listener);

        return () => listeners.delete(listener);
    },
};

// The preset a block uses, or null.
export function blockPreset(block: Block): string | null {
    const preset = block.attrs?.[PRESET_ATTR];

    return typeof preset === 'string' && preset !== '' ? preset : null;
}

// The block with a new preset. Null removes it.
export function withPreset(block: Block, preset: string | null): Block {
    const attrs = { ...block.attrs };

    if (preset === null) {
        delete attrs[PRESET_ATTR];
    } else {
        attrs[PRESET_ATTR] = preset;
    }

    return { ...block, attrs };
}

function isPlainObject(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

// The block styles on top of the styles of a preset, like array_replace_recursive() in PHP.
export function mergeDesign(base: Design, over: Design): Design {
    const result: Design = { ...base };

    Object.entries(over).forEach(([key, value]) => {
        const current = result[key];
        result[key] =
            isPlainObject(current) && isPlainObject(value)
                ? mergeDesign(current, value)
                : value;
    });

    return result;
}
