import { act, fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { builder } from '@/builder';
import type { EditorManifest } from '@/builder';
import { codeToBlocks, parseCode, serializeBlocks } from '@/builder/code';
import { registerPluginBlocks } from '@/builder/core';
import { resetBuilder, setupBuilder } from '@/builder/setup';
import { BlockEditor } from '@/components/verso/editor/block-editor';
import type { Block } from '@/types';

vi.mock('@/components/verso/editor/code-view', () => ({
    default: ({
        value,
        onChange,
    }: {
        value: string;
        onChange: (value: string) => void;
    }) => (
        <textarea
            aria-label="Page code"
            value={value}
            onChange={(event) => onChange(event.target.value)}
        />
    ),
}));

const fetchMock = vi.fn();

// Realistic content for each core block, so the round trip covers real values.
const SAMPLES: Record<string, Partial<Block>> = {
    paragraph: {
        content:
            '<p>Fresh bread from <strong>6 am</strong>, <a href="/menu">see the menu</a>.</p>',
    },
    heading: { attrs: { level: 3 }, content: 'Opening <em>hours</em>' },
    list: {
        attrs: { ordered: true },
        content: '<li>Sourdough</li><li>Rye</li>',
    },
    quote: {
        attrs: { citation: 'Ana, regular' },
        content: '<p>Best croissants in town.</p>',
    },
    code: {
        attrs: { language: 'html' },
        content: '<div class="menu">\n  <!-- not a marker -->\n</div>',
    },
    html: {
        content:
            '<div class="map"><iframe src="https://maps.example/embed"></iframe></div>',
    },
    element: {
        attrs: { tag: 'nav', attributes: { 'aria-label': 'Footer -- links' } },
        content: 'Find us',
    },
    button: {
        attrs: { label: 'Book a table', url: '/book', style: 'primary' },
    },
};

function sample(type: string): Block {
    const block = builder.blocks.get(type)!.create();

    return {
        ...block,
        ...SAMPLES[type],
        attrs: { ...block.attrs, ...SAMPLES[type]?.attrs },
    };
}

const manifest: EditorManifest = {
    version: 1,
    categories: [],
    styleProperties: [],
    breakpoints: [],
    features: ['code_editor'],
    allowedBlocks: null,
    limits: { colors: null, fonts: null, spacing: null },
};

beforeEach(() => {
    resetBuilder();
    setupBuilder({});
    registerPluginBlocks(builder, [
        {
            name: 'section',
            label: 'Section',
            description: 'A box.',
            fields: [],
            children: true,
        },
    ]);
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
});

describe('round trip', () => {
    it.each([
        'paragraph',
        'heading',
        'list',
        'quote',
        'code',
        'image',
        'gallery',
        'embed',
        'video',
        'columns',
        'table',
        'button',
        'separator',
        'html',
        'element',
        'section',
    ])('keeps a %s block the same through code', (type) => {
        const block = sample(type);
        const parsed = parseCode(serializeBlocks([block]));

        expect(parsed).toEqual({ ok: true, items: [block] });
    });

    it('covers every registered block type', () => {
        const types = builder.blocks
            .all()
            .map((block) => block.type)
            .filter((type) => type !== 'column');

        types.forEach((type) => {
            const block = sample(type);
            expect(parseCode(serializeBlocks([block]))).toEqual({
                ok: true,
                items: [block],
            });
        });
    });

    it('keeps nesting, ids and styles of a landing page', () => {
        const page: Block[] = [
            {
                id: 'b_hero',
                type: 'section',
                attrs: {
                    tag: 'header',
                    design: {
                        spacing: {
                            padding: { top: { base: '6rem', mobile: '3rem' } },
                        },
                    },
                },
                content: '',
                children: [
                    {
                        id: 'b_title',
                        type: 'heading',
                        attrs: { level: 1 },
                        content: 'Harbor Bakery',
                        children: [],
                    },
                    {
                        id: 'b_cta',
                        type: 'button',
                        attrs: {
                            label: 'Order now',
                            url: '/order',
                            style: 'primary',
                        },
                        content: '',
                        children: [],
                    },
                ],
            },
            {
                id: 'b_sep',
                type: 'separator',
                attrs: {},
                content: '',
                children: [],
            },
        ];
        const code = serializeBlocks(page);

        expect(code).toContain(
            '<!-- verso:heading#b_title {"level":1} -->\n<h1>Harbor Bakery</h1>\n  <!-- /verso:heading -->',
        );
        expect(code).toContain('<!-- verso:separator#b_sep /-->');
        expect(parseCode(code)).toEqual({ ok: true, items: page });
    });

    it('reads a heading level from the tag people typed', () => {
        const parsed = parseCode(
            '<!-- verso:heading#b_t {"level":2} -->\n<h4>Gift cards</h4>\n<!-- /verso:heading -->',
        );

        expect(parsed.ok && parsed.items[0]).toMatchObject({
            attrs: { level: 4 },
            content: 'Gift cards',
        });
    });

    it('gives new ids to blocks without one or with a repeated one', () => {
        const parsed = parseCode(
            '<!-- verso:separator /-->\n<!-- verso:separator#b_a /-->\n<!-- verso:separator#b_a /-->',
        );
        const ids = parsed.ok
            ? parsed.items.map((item) => ('id' in item ? item.id : ''))
            : [];

        expect(new Set(ids).size).toBe(3);
        expect(ids[1]).toBe('b_a');
    });
});

describe('parse errors', () => {
    it.each([
        [
            '<!-- verso:heading {"level":2} -->\n<h2>Menu</h2>',
            1,
            'The "heading" block is never closed. Add <!-- /verso:heading --> after its content.',
        ],
        [
            '<p>Intro</p>\n\n<!-- /verso:paragraph -->',
            3,
            'This closes a "paragraph" block that was never opened.',
        ],
        [
            '<!-- verso:section -->\n<!-- verso:heading -->\nHi\n<!-- /verso:section -->',
            4,
            'This closes a "section" block, but the "heading" block from line 2 is still open.',
        ],
        ['\n<!-- verso:marquee /-->', 2, '"marquee" is not a known block.'],
        [
            '<!-- verso:heading {level:2} /-->',
            1,
            'The settings of the "heading" block are not valid JSON.',
        ],
        [
            '<!-- verso:heading ["a"] /-->',
            1,
            'The settings of the "heading" block are not valid JSON.',
        ],
    ])('reports the line of a problem', (code, line, message) => {
        expect(parseCode(code)).toEqual({
            ok: false,
            error: { line, message },
        });
    });
});

describe('codeToBlocks', () => {
    it('converts pasted HTML and Gutenberg markup between blocks', async () => {
        const convert = vi.fn(async (html: string): Promise<Block[]> => [
            {
                id: `b_${html.length}`,
                type: 'paragraph',
                attrs: {},
                content: html,
                children: [],
            },
        ]);
        const result = await codeToBlocks(
            '<p>Pasted intro</p>\n<!-- verso:separator#b_s /-->\n<!-- wp:paragraph --><p>From WordPress</p><!-- /wp:paragraph -->',
            convert,
        );

        expect(convert).toHaveBeenCalledTimes(2);
        expect(convert).toHaveBeenNthCalledWith(
            2,
            '<!-- wp:paragraph --><p>From WordPress</p><!-- /wp:paragraph -->',
        );
        expect(
            'blocks' in result && result.blocks.map((block) => block.type),
        ).toEqual(['paragraph', 'separator', 'paragraph']);
    });

    it('reports the line when the converter fails', async () => {
        const result = await codeToBlocks(
            '<!-- verso:separator /-->\n\n<div>Broken</div>',
            async () => {
                throw new Error('offline');
            },
        );

        expect(result).toEqual({
            error: {
                line: 3,
                message:
                    'This HTML could not be turned into blocks. Check it, or wrap it in block markers.',
            },
        });
    });
});

describe('Visual and Code switch', () => {
    function Harness({ editor = manifest }: { editor?: EditorManifest }) {
        const [blocks, setBlocks] = useState<Block[]>([
            {
                id: 'b_title',
                type: 'heading',
                attrs: { level: 2 },
                content: 'Our bread',
                children: [],
            },
        ]);

        return (
            <>
                <BlockEditor
                    value={blocks}
                    onChange={setBlocks}
                    editor={editor}
                />
                <output data-test="json">{JSON.stringify(blocks)}</output>
            </>
        );
    }

    const json = () =>
        JSON.parse(
            document.querySelector('[data-test="json"]')!.textContent ?? '[]',
        ) as Block[];

    it('is hidden without the code editor feature', () => {
        render(<Harness editor={{ ...manifest, features: [] }} />);

        expect(
            screen.queryByRole('button', { name: 'Code' }),
        ).not.toBeInTheDocument();
    });

    it('edits a heading in code and goes back to visual', async () => {
        render(<Harness />);
        await userEvent.click(screen.getByRole('button', { name: 'Code' }));
        const area = await screen.findByLabelText('Page code');

        expect((area as HTMLTextAreaElement).value).toContain(
            '<h2>Our bread</h2>',
        );
        fireEvent.change(area, {
            target: {
                value: (area as HTMLTextAreaElement).value.replace(
                    'Our bread',
                    'Our sourdough',
                ),
            },
        });
        await userEvent.click(screen.getByRole('button', { name: 'Visual' }));

        expect(json()[0]).toMatchObject({
            id: 'b_title',
            content: 'Our sourdough',
        });
        expect(screen.queryByLabelText('Page code')).not.toBeInTheDocument();
    });

    it('keeps the code and shows the line when it can not be read', async () => {
        render(<Harness />);
        await userEvent.click(screen.getByRole('button', { name: 'Code' }));
        const area = await screen.findByLabelText('Page code');
        fireEvent.change(area, {
            target: { value: '<!-- verso:heading -->\n<h2>Lost?</h2>' },
        });
        await userEvent.click(screen.getByRole('button', { name: 'Visual' }));

        expect(screen.getByRole('alert')).toHaveTextContent(
            'Line 1: The "heading" block is never closed.',
        );
        expect(screen.getByLabelText('Page code')).toHaveValue(
            '<!-- verso:heading -->\n<h2>Lost?</h2>',
        );
        expect(json()[0].content).toBe('Our bread');
    });

    it('asks the server to convert pasted HTML', async () => {
        fetchMock.mockResolvedValue({
            ok: true,
            json: async () => ({
                blocks: [
                    {
                        id: 'b_new',
                        type: 'paragraph',
                        attrs: {},
                        content: '<p>Pasted</p>',
                        children: [],
                    },
                ],
            }),
        });
        render(<Harness />);
        await userEvent.click(screen.getByRole('button', { name: 'Code' }));
        const area = await screen.findByLabelText('Page code');
        fireEvent.change(area, { target: { value: '<p>Pasted</p>' } });
        await act(async () => {
            await userEvent.click(
                screen.getByRole('button', { name: 'Visual' }),
            );
        });

        expect(fetchMock).toHaveBeenCalledWith(
            '/admin/editor/convert',
            expect.objectContaining({
                method: 'POST',
                body: JSON.stringify({ html: '<p>Pasted</p>' }),
            }),
        );
        expect(json().map((block) => block.id)).toEqual(['b_new']);
    });
});
