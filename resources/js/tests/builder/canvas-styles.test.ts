import { describe, expect, it } from 'vitest';
import {
    canvasClass,
    canvasStyleSheet,
    isRowLayout,
    styleOptionsFrom,
} from '@/builder/canvas-styles';
import type { Block } from '@/types';

const options = styleOptionsFrom([
    {
        path: 'spacing.padding.top',
        css: 'padding-top',
        kind: 'length',
        label: 'Padding top',
        group: 'spacing',
        control: 'length',
        options: [],
    },
    {
        path: 'color.background',
        css: 'background-color',
        kind: 'color',
        label: 'Background',
        group: 'color',
        control: 'color',
        options: [],
    },
    {
        path: 'layout.display',
        css: 'display',
        kind: 'display',
        label: 'Display',
        group: 'layout',
        control: 'select',
        options: [],
    },
    {
        path: 'layout.wrap',
        css: 'flex-wrap',
        kind: 'wrap',
        label: 'Wrap',
        group: 'layout',
        control: 'select',
        options: [],
    },
    {
        path: 'layout.columns',
        css: 'grid-template-columns',
        kind: 'columns',
        label: 'Columns',
        group: 'layout',
        control: 'number',
        options: [],
    },
    {
        path: 'layout.gap',
        css: 'gap',
        kind: 'length',
        label: 'Gap',
        group: 'layout',
        control: 'length',
        options: [],
    },
    {
        path: 'visibility.hidden',
        css: 'display',
        kind: 'hidden',
        label: 'Hidden',
        group: 'visibility',
        control: 'toggle',
        options: [],
    },
    {
        path: 'plugin.glow',
        kind: 'length',
        label: 'Glow',
        group: 'plugin',
        control: 'length',
        options: [],
    },
]);

const bakery: Block[] = [
    {
        id: 'b_hero',
        type: 'section',
        attrs: {
            design: {
                spacing: { padding: { top: { base: '6rem', mobile: '2rem' } } },
                color: { background: '#F4EFE6' },
                layout: {
                    display: 'grid',
                    columns: { base: 3, mobile: 1 },
                    gap: '2rem',
                },
            },
        },
        children: [
            {
                id: 'b_rye',
                type: 'paragraph',
                attrs: {},
                content: 'Rye sourdough',
                children: [],
            },
            {
                id: 'b_promo',
                type: 'paragraph',
                attrs: { design: { visibility: { hidden: { mobile: true } } } },
                content: 'Free coffee before 8',
                children: [],
            },
        ],
    },
];

describe('canvasStyleSheet', () => {
    it('styles the frame and puts the layout on the child list on a computer', () => {
        expect(
            canvasStyleSheet(bakery, {
                options,
                device: 'base',
                customCssMax: 2000,
            }),
        ).toBe(
            [
                '.vs-e-b_hero{padding-top:6rem;background-color:#f4efe6}',
                '.vs-e-b_hero-c{display:grid;grid-template-columns:repeat(3, minmax(0, 1fr));gap:2rem}',
            ].join('\n'),
        );
    });

    it('previews the phone values and fades blocks hidden on phones', () => {
        const css = canvasStyleSheet(bakery, {
            options,
            device: 'mobile',
            customCssMax: 2000,
        });

        expect(css).toContain(
            '.vs-e-b_hero{padding-top:6rem;background-color:#f4efe6;padding-top:2rem}',
        );
        expect(css).toContain(
            'grid-template-columns:repeat(1, minmax(0, 1fr))',
        );
        expect(css).toContain('.vs-e-b_promo{opacity:0.45}');
        expect(css).not.toContain('display:none');
    });

    it('leaves out options without a CSS property and blocks without styles', () => {
        expect(
            canvasStyleSheet(
                [
                    {
                        id: 'b_note',
                        type: 'paragraph',
                        attrs: { design: { plugin: { glow: '4px' } } },
                        content: '',
                        children: [],
                    },
                ],
                { options, device: 'base', customCssMax: 2000 },
            ),
        ).toBe('');
    });

    it('keeps layout on an empty section when it holds children', () => {
        const css = canvasStyleSheet(
            [
                {
                    id: 'b_empty',
                    type: 'section',
                    attrs: { design: { layout: { display: 'flex' } } },
                    children: [],
                },
            ],
            {
                options,
                device: 'base',
                customCssMax: 2000,
                holdsChildren: (block) => block.type === 'section',
            },
        );

        expect(css).toBe(
            '.vs-e-b_empty-c{display:flex;flex-wrap:wrap}\n.vs-e-b_empty-c>[data-block-id]{flex:1 1 14rem;min-inline-size:0;margin-block-start:0}',
        );
    });

    it('lets blocks in a row wrap in the canvas, so their fields keep room', () => {
        const css = canvasStyleSheet(
            [
                {
                    id: 'b_header',
                    type: 'section',
                    attrs: {
                        design: {
                            layout: {
                                display: 'flex',
                                wrap: 'nowrap',
                            },
                        },
                    },
                    children: [],
                },
            ],
            {
                options,
                device: 'base',
                customCssMax: 2000,
                holdsChildren: () => true,
            },
        );

        expect(css).toContain('flex-wrap:wrap');
        expect(css).not.toContain('flex-wrap:nowrap');
        expect(css).toContain(
            '.vs-e-b_header-c>[data-block-id]{flex:1 1 14rem',
        );
    });

    it('leaves column and grid layouts as they are', () => {
        expect(isRowLayout(['display:flex', 'flex-direction:column'])).toBe(
            false,
        );
        expect(isRowLayout(['display:grid'])).toBe(false);
        expect(isRowLayout(['display:flex'])).toBe(true);
        expect(
            isRowLayout(['display:inline-flex', 'flex-direction:row-reverse']),
        ).toBe(true);
    });
});

describe('canvasClass', () => {
    it('makes ids safe for a CSS selector', () => {
        expect(canvasClass('b_9f:2.x')).toBe('vs-e-b_9f_2_x');
    });
});
