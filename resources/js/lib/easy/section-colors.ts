import { msg, t } from '@/lib/i18n';
import type { Block } from '@/types';
import { isHexColor, normalizeHex } from './color';
import { BODY_TEXT, contrastRatio, fixColor, LARGE_TEXT } from './contrast';
import { updateBlock } from './page-blocks';

// Color checks for the page editor: text in a section that sets its own text or background color
// (attrs.design.color, a token like "var:color.accent" or a hex color) must stay readable on the current style.

// Color token values of the site, like {"color.text": "#1b2420"}.
export type SiteColors = Record<string, string>;

// Blocks under one "origin" (the block that set the colors) that are hard to read on one background.
export type ColorGroup = {
    originId: string;
    foreground: string;
    background: string;
    minimum: number;
    ratio: number;
};

export type SectionColorWarning = {
    sectionId: string;
    message: string;
    groups: ColorGroup[];
};

export const SECTION_COLOR_MESSAGE = msg(
    'Some text in this section is hard to read on its background.',
);

const TOKEN = /^var:(color\.[a-z0-9-]+)$/;

// Blocks that show text to visitors.
const TEXT_TYPES = new Set([
    'heading',
    'paragraph',
    'list',
    'quote',
    'button',
    'table',
]);

// Token order tried by "Fix colors": the usual text color first, then the other colors of the style.
const FIX_TOKENS = [
    'color.text',
    'color.background',
    'color.surface',
    'color.muted',
    'color.accent',
];

// The colors a block draws with. "unknown" means a color the editor cannot read, like a missing token: nothing below is checked.
type Paint = {
    foreground?: string;
    background?: string;
    origin?: string;
    unknown?: boolean;
};

type Design = { color?: { text?: unknown; background?: unknown } };

// The base value of a style option that may be responsive ({"base": ..., "mobile": ...}).
function baseValue(value: unknown): unknown {
    return value !== null && typeof value === 'object' && 'base' in value
        ? (value as { base: unknown }).base
        : value;
}

// A color option as a hex color, or undefined when it is not set or not a plain color (like a gradient).
export function resolveColor(
    value: unknown,
    colors: SiteColors,
): string | undefined {
    const base = baseValue(value);

    if (typeof base !== 'string') {
        return undefined;
    }

    const token = TOKEN.exec(base.trim());
    const hex = token ? colors[token[1]] : base;

    return hex && isHexColor(hex)
        ? (normalizeHex(hex) ?? undefined)
        : undefined;
}

function designColors(block: Block): Design['color'] {
    return (block.attrs?.design as Design | undefined)?.color;
}

// True when a color option is set but is not a color the editor can read.
function unreadable(value: unknown, colors: SiteColors): boolean {
    const base = baseValue(value);

    return (
        base !== undefined &&
        base !== null &&
        base !== '' &&
        resolveColor(base, colors) === undefined
    );
}

// The background a button draws by itself: the accent color for the main button style.
function buttonBackground(
    block: Block,
    colors: SiteColors,
): string | undefined {
    return block.type === 'button' && block.attrs?.style !== 'secondary'
        ? resolveColor('var:color.accent', colors)
        : undefined;
}

// Walks one section and collects its text blocks that are hard to read, grouped by the block that set the colors.
function reviewSection(section: Block, colors: SiteColors): ColorGroup[] {
    const groups = new Map<string, ColorGroup>();
    const page: Paint = {
        foreground: resolveColor('var:color.text', colors),
        background: resolveColor('var:color.background', colors),
    };

    const walk = (block: Block, inherited: Paint) => {
        const own = designColors(block);
        const ownText = resolveColor(own?.text, colors);
        const ownBackground =
            resolveColor(own?.background, colors) ??
            (ownText ? buttonBackground(block, colors) : undefined);
        const paint: Paint = {
            foreground: ownText ?? inherited.foreground,
            background: ownBackground ?? inherited.background,
            origin: ownText || ownBackground ? block.id : inherited.origin,
            unknown:
                inherited.unknown ||
                unreadable(own?.text, colors) ||
                unreadable(own?.background, colors),
        };

        if (
            !paint.unknown &&
            TEXT_TYPES.has(block.type) &&
            paint.origin &&
            paint.foreground &&
            paint.background &&
            (block.type !== 'button' || ownText || ownBackground)
        ) {
            const minimum = block.type === 'heading' ? LARGE_TEXT : BODY_TEXT;
            const ratio = contrastRatio(paint.foreground, paint.background);
            const known = groups.get(paint.origin);

            if (ratio < minimum) {
                groups.set(paint.origin, {
                    originId: paint.origin,
                    foreground: paint.foreground,
                    background: paint.background,
                    minimum: Math.max(minimum, known?.minimum ?? 0),
                    ratio:
                        Math.round(
                            Math.min(ratio, known?.ratio ?? ratio) * 100,
                        ) / 100,
                });
            }
        }

        block.children?.forEach((child) => walk(child, paint));
    };

    walk(section, page);

    return [...groups.values()];
}

// One warning per top level section whose own colors make some text hard to read.
export function reviewSectionColors(
    blocks: Block[],
    colors: SiteColors,
): SectionColorWarning[] {
    return blocks.flatMap((section) => {
        const groups = reviewSection(section, colors);

        return groups.length === 0
            ? []
            : [
                  {
                      sectionId: section.id,
                      message: t(SECTION_COLOR_MESSAGE),
                      groups,
                  },
              ];
    });
}

// The text color "Fix colors" picks: a color of the style that reads well, else the old color made darker or lighter.
export function readableTextColor(
    group: Pick<ColorGroup, 'foreground' | 'background' | 'minimum'>,
    colors: SiteColors,
): string {
    const tokens = [
        ...FIX_TOKENS,
        ...Object.keys(colors).filter(
            (id) => id.startsWith('color.') && !FIX_TOKENS.includes(id),
        ),
    ];

    for (const id of tokens) {
        const hex = resolveColor(`var:${id}`, colors);

        if (hex && contrastRatio(hex, group.background) >= group.minimum) {
            return `var:${id}`;
        }
    }

    return fixColor(group.foreground, group.background, group.minimum);
}

// Sets attrs.design.color.text of a block, keeping its other style options.
function withTextColor(block: Block, value: string): Block {
    const design = { ...(block.attrs?.design as Record<string, unknown>) };
    const color = { ...(design.color as Record<string, unknown>) };
    const current = color.text;

    color.text =
        current !== null && typeof current === 'object'
            ? { ...(current as Record<string, unknown>), base: value }
            : value;

    return {
        ...block,
        attrs: { ...block.attrs, design: { ...design, color } },
    };
}

// The blocks with the text colors of one warning made readable. Nothing else changes.
export function fixSectionColors(
    blocks: Block[],
    warning: SectionColorWarning,
    colors: SiteColors,
): Block[] {
    return warning.groups.reduce(
        (current, group) =>
            updateBlock(current, group.originId, (block) =>
                withTextColor(block, readableTextColor(group, colors)),
            ),
        blocks,
    );
}
