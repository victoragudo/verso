import { cssValueRule, normalizeCssValue } from '@/lib/css-value';
import type { CssValueRule } from '@/lib/css-value';
import {
    DESIGN_ATTR,
    mergeDesign,
    PRESET_ATTR,
    STYLE_KEYWORD_CSS,
    styleValueRule,
} from './styles';
import type { Design } from './styles';
import { focalCss, gradientCss, imageCss, zIndexCss } from './style-values';

// The CSS rules of block styles, made the same way as BlockStyleCompiler in PHP.
// tests/Fixtures/block-styles.json holds cases that both the PHP and the TS tests check, so the two never drift.

// Attr that holds the admin only custom CSS of a block (same as BlockStyleSchema::CUSTOM_CSS_ATTR).
export const CUSTOM_CSS_ATTR = 'customCss';

// Screen sizes a responsive value can name, from the biggest to the smallest.
export const STYLE_DEVICES = ['base', 'tablet', 'mobile'];

// Option path => [CSS property, value kind, value rule of size kinds].
export type StyleOptions = Record<
    string,
    readonly [string, string, (Partial<CssValueRule> | null)?]
>;

// CSS declarations of one block, grouped by screen size.
export type Declarations = Record<string, string[]>;

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
const BLOCKED_CSS = [
    '{',
    '}',
    '<',
    '>',
    '@',
    '\\',
    'url(',
    'expression(',
    'javascript:',
    'behavior',
    '-moz-binding',
];
const CUSTOM_LINE = /^\s*(-?[a-z][a-z-]{0,40})\s*:\s*([^;]{1,300})$/i;

function isObject(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function read(style: Record<string, unknown>, path: string): unknown {
    return path
        .split('.')
        .reduce<unknown>(
            (value, key) => (isObject(value) ? value[key] : undefined),
            style,
        );
}

// The safe CSS value for a stored value, or null when it is not allowed. Same as BlockStyleSchema::css().
export function styleCssValue(
    kind: string,
    value: unknown,
    rule?: Partial<CssValueRule> | null,
): string | null {
    if (TOKEN_KINDS.includes(kind) && typeof value === 'string') {
        const token = TOKEN.exec(value);

        if (token) {
            return `var(--verso-${token[1]}-${token[2]})`;
        }
    }

    const isInt = typeof value === 'number' && Number.isInteger(value);

    switch (kind) {
        case 'length':
        case 'angle':
        case 'time':
            return normalizeCssValue(
                value,
                styleValueRule(kind, rule ? cssValueRule(rule) : null),
            );
        case 'color':
        case 'overlay':
            return typeof value === 'string' && HEX.test(value)
                ? value.toLowerCase()
                : null;
        case 'number':
            return typeof value === 'number' && value > 0 && value <= 4
                ? String(value)
                : null;
        case 'weight':
            return isInt && value >= 100 && value <= 900 && value % 100 === 0
                ? String(value)
                : null;
        case 'columns':
            return isInt && value >= 1 && value <= 12
                ? `repeat(${value}, minmax(0, 1fr))`
                : null;
        case 'sticky':
            return typeof value === 'boolean'
                ? value
                    ? 'sticky'
                    : 'static'
                : null;
        case 'hidden':
            return typeof value === 'boolean' ? (value ? 'none' : '') : null;
        case 'gradient':
            return gradientCss(value);
        case 'image':
            return imageCss(value);
        case 'focal':
            return focalCss(value);
        case 'zIndex':
            return zIndexCss(value);
        case 'token':
            return null;
        default:
            return typeof value === 'string'
                ? (STYLE_KEYWORD_CSS[kind]?.[value] ?? null)
                : null;
    }
}

// A value split by screen size. A plain value is the "base" value. An empty key means an unknown size.
function byDevice(value: unknown): [string, unknown][] {
    if (!isObject(value)) {
        return [['base', value]];
    }

    return Object.entries(value).map(([device, single]) => [
        STYLE_DEVICES.includes(device) ? device : '',
        single,
    ]);
}

function isGroup(options: StyleOptions, path: string): boolean {
    return Object.keys(options).some((option) => option.startsWith(`${path}.`));
}

// True when every path is a known option and every value is allowed. PHP drops all styles of a block otherwise.
function isValidDesign(
    options: StyleOptions,
    style: Record<string, unknown>,
    prefix = '',
): boolean {
    return Object.entries(style).every(([key, value]) => {
        const path = prefix + key;
        const option = options[path];

        if (option) {
            return byDevice(value).every(
                ([device, single]) =>
                    device !== '' &&
                    styleCssValue(option[1], single, option[2]) !== null,
            );
        }

        return isObject(value) && isGroup(options, path)
            ? isValidDesign(options, value, `${path}.`)
            : false;
    });
}

// Keeps only "property: value;" lines with safe text. Same as BlockStyleCompiler::sanitizeCustomCss().
export function sanitizeCustomCss(css: unknown, max: number): string {
    if (typeof css !== 'string' || css.trim() === '') {
        return '';
    }

    const text = Array.from(css.replace(/[\r\n]/g, ' '))
        .slice(0, max)
        .join('');
    const blocked = (part: string) =>
        BLOCKED_CSS.some((word) => part.toLowerCase().includes(word));

    return text
        .split(';')
        .map((line) => CUSTOM_LINE.exec(line))
        .filter(
            (match): match is RegExpExecArray =>
                match !== null && !blocked(match[2]) && !blocked(match[1]),
        )
        .map((match) => `${match[1].toLowerCase()}:${match[2].trim()}`)
        .join(';');
}

// A gradient and a picture both set background-image: one line with the gradient on top. Same as PHP joinBackgrounds().
function joinBackgrounds(lines: string[]): string[] {
    const prefix = 'background-image:';
    const images = lines.filter((line) => line.startsWith(prefix));

    if (images.length < 2) {
        return lines;
    }

    const joined =
        prefix + images.map((line) => line.slice(prefix.length)).join(', ');
    const first = lines.indexOf(images[0]);

    return lines.flatMap((line, index) =>
        index === first ? [joined] : line.startsWith(prefix) ? [] : [line],
    );
}

// Style presets by key: the styles each one adds under the block styles.
export type StylePresetDesigns = Record<string, Design>;

// The CSS declarations of a block by screen size, in the order of the options. Empty sizes are left out.
export function blockDeclarations(
    attrs: Record<string, unknown> | undefined,
    options: StyleOptions,
    customCssMax: number,
    presets: StylePresetDesigns = {},
): Declarations {
    const declarations: Declarations = Object.fromEntries(
        STYLE_DEVICES.map((device) => [device, [] as string[]]),
    );
    const own = isObject(attrs?.[DESIGN_ATTR]) ? attrs[DESIGN_ATTR] : {};
    const preset = attrs?.[PRESET_ATTR];
    const style =
        typeof preset === 'string' && presets[preset]
            ? mergeDesign(presets[preset], own)
            : own;

    if (isValidDesign(options, style)) {
        Object.entries(options).forEach(([path, [property, kind, rule]]) => {
            const value = read(style, path);

            if (value === undefined || value === null) {
                return;
            }

            byDevice(value).forEach(([device, single]) => {
                const css = styleCssValue(kind, single, rule);

                if (device !== '' && css !== null && css !== '') {
                    declarations[device].push(
                        `${property}:${kind === 'overlay' ? `inset 0 0 0 100vmax ${css}` : css}`,
                    );
                }
            });
        });
    }

    STYLE_DEVICES.forEach((device) => {
        declarations[device] = joinBackgrounds(declarations[device]);
    });

    const custom = sanitizeCustomCss(attrs?.[CUSTOM_CSS_ATTR], customCssMax);

    if (custom !== '') {
        declarations.base.push(custom);
    }

    return Object.fromEntries(
        Object.entries(declarations).filter(([, lines]) => lines.length > 0),
    );
}

// The rules with media queries, exactly as the public site prints them for one class.
export function siteRules(
    className: string,
    declarations: Declarations,
    breakpoints: Record<string, number>,
): string {
    return Object.entries(declarations)
        .map(([device, lines]) => {
            const rule = `.${className}{${lines.join(';')}}`;
            const width = breakpoints[device];

            return device === 'base' || width === undefined
                ? rule
                : `@media (max-width:${width}px){${rule}}`;
        })
        .join('');
}

// The declarations that apply on one screen size, without media queries: the bigger sizes first, so the smaller ones win.
export function deviceDeclarations(
    declarations: Declarations,
    device: string,
): string[] {
    const last = Math.max(STYLE_DEVICES.indexOf(device), 0);

    return STYLE_DEVICES.slice(0, last + 1).flatMap(
        (size) => declarations[size] ?? [],
    );
}
