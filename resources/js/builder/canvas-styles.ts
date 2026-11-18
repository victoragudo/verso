import type { Block } from '@/types';
import { blockDeclarations, deviceDeclarations } from './style-css';
import type { StyleOptions, StylePresetDesigns } from './style-css';
import type { BuilderStyleProperty } from './types';

// Prefix of the classes the canvas gives to styled blocks. The site uses "vs-<hash>"; the canvas uses the block id.
export const CANVAS_CLASS_PREFIX = 'vs-e-';

// Suffix of the class on the list that holds the child blocks of a styled block.
export const CANVAS_CHILDREN_SUFFIX = '-c';

// CSS properties that place child blocks. In the canvas they go on the list of child blocks, not on the block frame.
const CHILD_LAYOUT_PROPERTIES = [
    'display',
    'flex-direction',
    'flex-wrap',
    'gap',
    'align-items',
    'justify-content',
    'grid-template-columns',
];

// In the canvas, blocks side by side get room for their fields: each takes at least this width and the row wraps.
export const CANVAS_ROW_ITEM_MIN = '14rem';

// True when the layout lines put the child blocks in a row (flex without a column direction).
export function isRowLayout(lines: string[]): boolean {
    const value = (property: string) =>
        lines
            .find((line) => line.split(':')[0] === property)
            ?.slice(property.length + 1)
            .trim();
    const display = value('display') ?? '';
    const direction = value('flex-direction') ?? 'row';

    return display.endsWith('flex') && direction.startsWith('row');
}

// What "hidden on this screen" looks like in the canvas: faded, so people can still pick the block.
const HIDDEN_ON_SITE = 'display:none';
const HIDDEN_IN_CANVAS = 'opacity:0.45';

export type CanvasStyleOptions = {
    options: StyleOptions;
    device: string;
    customCssMax: number;
    // Style presets by key, so blocks with attrs.preset show its styles.
    presets?: StylePresetDesigns;
    // True for blocks that hold other blocks, like a section. By default: blocks that have children now.
    holdsChildren?: (block: Block) => boolean;
};

// The class of one block in the canvas. Ids are made safe for a CSS selector.
export function canvasClass(id: string): string {
    return CANVAS_CLASS_PREFIX + id.replace(/[^a-zA-Z0-9_-]/g, '_');
}

// Option path => [CSS property, kind, value rule], from the style options the server sent.
export function styleOptionsFrom(
    properties: BuilderStyleProperty[],
): StyleOptions {
    return Object.fromEntries(
        properties
            .filter((property) => property.css)
            .map((property) => [
                property.path,
                [
                    property.css as string,
                    property.kind,
                    property.value ?? null,
                ] as const,
            ]),
    );
}

// True when the block carries styles, so it needs a canvas class.
export function hasCanvasStyles(block: Block): boolean {
    return Boolean(
        block.attrs?.design || block.attrs?.customCss || block.attrs?.preset,
    );
}

// One style sheet for every styled block of the tree, with the values of the chosen screen size.
export function canvasStyleSheet(
    blocks: Block[],
    {
        options,
        device,
        customCssMax,
        presets = {},
        holdsChildren = (block) => (block.children?.length ?? 0) > 0,
    }: CanvasStyleOptions,
): string {
    const rules: string[] = [];

    const visit = (block: Block) => {
        if (hasCanvasStyles(block)) {
            const lines = deviceDeclarations(
                blockDeclarations(block.attrs, options, customCssMax, presets),
                device,
            ).map((line) =>
                line === HIDDEN_ON_SITE ? HIDDEN_IN_CANVAS : line,
            );
            const hasChildren = holdsChildren(block);
            const isLayout = (line: string) =>
                hasChildren &&
                CHILD_LAYOUT_PROPERTIES.includes(line.split(':')[0]);
            const frame = lines.filter((line) => !isLayout(line));
            const layout = lines.filter(isLayout);
            const name = canvasClass(block.id);

            if (frame.length > 0) {
                rules.push(`.${name}{${frame.join(';')}}`);
            }

            if (layout.length > 0) {
                const row = isRowLayout(layout);
                const lines = row
                    ? [
                          ...layout.filter(
                              (line) => line.split(':')[0] !== 'flex-wrap',
                          ),
                          'flex-wrap:wrap',
                      ]
                    : layout;
                rules.push(
                    `.${name}${CANVAS_CHILDREN_SUFFIX}{${lines.join(';')}}`,
                );

                if (row) {
                    rules.push(
                        `.${name}${CANVAS_CHILDREN_SUFFIX}>[data-block-id]{flex:1 1 ${CANVAS_ROW_ITEM_MIN};min-inline-size:0;margin-block-start:0}`,
                    );
                }
            }
        }

        (block.children ?? []).forEach(visit);
    };

    blocks.forEach(visit);

    return rules.join('\n');
}
