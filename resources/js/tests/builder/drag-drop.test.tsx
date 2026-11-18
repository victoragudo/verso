import { act, fireEvent, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { EditorManifest } from '@/builder';
import { dragState } from '@/builder/dnd';
import { resetBuilder } from '@/builder/setup';
import {
    filterInsertItems,
    readRecent,
    rememberRecent,
} from '@/components/verso/editor/add-panel';
import { BlockEditor } from '@/components/verso/editor/block-editor';
import { outlinePreview } from '@/components/verso/editor/outline-view';
import type { Block } from '@/types';
import { Pilcrow } from 'lucide-react';

const manifest: EditorManifest = {
    version: 1,
    categories: [],
    styleProperties: [],
    breakpoints: [{ key: 'base', maxWidth: null }],
    features: ['add_panel', 'list_view'],
    allowedBlocks: null,
    recentLimit: 3,
    limits: { colors: null, fonts: null, spacing: null },
};

const start: Block[] = [
    {
        id: 'b_hero',
        type: 'heading',
        attrs: { level: 1 },
        content: 'Harbor Bakery',
        children: [],
    },
    {
        id: 'b_cols',
        type: 'columns',
        attrs: {},
        content: '',
        children: [
            {
                id: 'b_left',
                type: 'column',
                attrs: {},
                content: '',
                children: [],
            },
            {
                id: 'b_right',
                type: 'column',
                attrs: {},
                content: '',
                children: [],
            },
        ],
    },
    { id: 'b_note', type: 'separator', attrs: {}, content: '', children: [] },
];

let latest: Block[] = [];

// Finds an element by its data-test attribute, the project test hook.
function byTest(name: string): HTMLElement {
    const element = document.querySelector<HTMLElement>(
        `[data-test="${name}"]`,
    );

    if (!element) {
        throw new Error(`No element with data-test="${name}".`);
    }

    return element;
}

function Harness({
    initial = start,
    editor = manifest,
}: {
    initial?: Block[];
    editor?: EditorManifest;
}) {
    const [blocks, setBlocks] = useState(initial);
    latest = blocks;

    return <BlockEditor value={blocks} onChange={setBlocks} editor={editor} />;
}

function dataTransfer() {
    return {
        setData: vi.fn(),
        getData: vi.fn(),
        types: ['application/x-verso-block'],
        effectAllowed: 'all',
        dropEffect: 'move',
    };
}

afterEach(() => {
    vi.useRealTimers();
});

beforeEach(() => {
    resetBuilder();
    window.localStorage.clear();
    latest = [];
});

describe('Add panel', () => {
    it('only shows the panel buttons for allowed features', () => {
        render(<Harness editor={{ ...manifest, features: [] }} />);

        expect(
            screen.queryByRole('button', { name: 'Add blocks' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Outline' }),
        ).not.toBeInTheDocument();
    });

    it('lists blocks by category and searches them', async () => {
        render(<Harness />);
        await userEvent.click(
            screen.getByRole('button', { name: 'Add blocks' }),
        );
        const panel = byTest('add-panel');

        expect(
            within(panel).getByRole('heading', { name: 'Text' }),
        ).toBeInTheDocument();
        expect(
            within(panel).getByRole('heading', { name: 'Media' }),
        ).toBeInTheDocument();
        await userEvent.type(within(panel).getByRole('searchbox'), 'gallery');
        expect(within(panel).getAllByRole('listitem')).toHaveLength(1);
        await userEvent.clear(within(panel).getByRole('searchbox'));
        await userEvent.type(
            within(panel).getByRole('searchbox'),
            'spreadsheet calendar',
        );
        expect(
            within(panel).getByText('Nothing found. Try another word.'),
        ).toBeInTheDocument();
    });

    it('drags a block from the panel into an empty column', async () => {
        render(<Harness />);
        await userEvent.click(
            screen.getByRole('button', { name: 'Add blocks' }),
        );
        const quote = byTest('add-panel').querySelector(
            '[data-insert-item="block:quote"]',
        )!;
        const transfer = dataTransfer();
        fireEvent.dragStart(quote, { dataTransfer: transfer });
        const zones = screen.getAllByText('Drop here');
        fireEvent.dragOver(zones[1], { dataTransfer: transfer });
        fireEvent.drop(zones[1], { dataTransfer: transfer });
        fireEvent.dragEnd(quote, { dataTransfer: transfer });

        expect(latest[1].children?.[1].children?.[0].type).toBe('quote');
        expect(dragState.get().payload).toBeNull();
        expect(readRecent()).toEqual(['block:quote']);
    });

    it('refuses to drop columns inside a column', async () => {
        render(<Harness />);
        await userEvent.click(
            screen.getByRole('button', { name: 'Add blocks' }),
        );
        const columns = byTest('add-panel').querySelector(
            '[data-insert-item="block:columns"]',
        )!;
        const transfer = dataTransfer();
        fireEvent.dragStart(columns, { dataTransfer: transfer });
        const zone = screen.getAllByText('Drop here')[0];
        fireEvent.dragOver(zone, { dataTransfer: transfer });

        expect(dragState.get().spot?.parentId).not.toBe('b_left');
        fireEvent.dragEnd(columns, { dataTransfer: transfer });
    });

    it('adds a clicked item after the selected block', async () => {
        render(<Harness />);
        fireEvent.pointerDown(
            screen.getByRole('button', { name: 'Drag to move Heading block' }),
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Add blocks' }),
        );
        await userEvent.click(
            byTest('add-panel').querySelector(
                '[data-insert-item="block:button"]',
            )!,
        );

        expect(latest.map((item) => item.type)).toEqual([
            'heading',
            'button',
            'columns',
            'separator',
        ]);
    });
});

describe('moving blocks', () => {
    it('drags a block in the canvas to another place', () => {
        render(<Harness />);
        const handle = screen.getByRole('button', {
            name: 'Drag to move Separator block',
        });
        const transfer = dataTransfer();
        fireEvent.dragStart(handle, { dataTransfer: transfer });
        const heading = document.querySelector(
            '[data-test="block-editor"] [data-block-id="b_hero"]',
        )!;
        fireEvent.dragOver(heading, { dataTransfer: transfer, clientY: 0 });
        fireEvent.drop(heading, { dataTransfer: transfer });

        // jsdom has no pointer position on drag events, so the drop lands after the heading.
        expect(latest.map((item) => item.id)).toEqual([
            'b_hero',
            'b_note',
            'b_cols',
        ]);
    });

    it('moves the selected block with keyboard shortcuts and announces it', async () => {
        vi.useFakeTimers();
        render(<Harness />);
        const handle = screen.getByRole('button', {
            name: 'Drag to move Separator block',
        });
        fireEvent.pointerDown(handle);
        fireEvent.keyDown(handle, {
            key: 'ArrowUp',
            altKey: true,
            shiftKey: true,
        });

        expect(latest.map((item) => item.id)).toEqual([
            'b_hero',
            'b_note',
            'b_cols',
        ]);
        await act(async () => vi.advanceTimersByTime(200));
        expect(
            screen.getByText('Separator moved to position 2 of 3 in the page.'),
        ).toBeInTheDocument();
        vi.useRealTimers();
    });

    it('moves a block into the column above from the block menu', async () => {
        render(<Harness />);
        await userEvent.click(
            screen.getByRole('button', { name: 'Separator block options' }),
        );
        await userEvent.click(
            screen.getByRole('menuitem', { name: 'Move into the block above' }),
        );

        expect(
            latest[1].children?.[1].children?.map((item) => item.id),
        ).toEqual(['b_note']);
    });
});

describe('Outline', () => {
    it('shows nested blocks and moves them from the outline menu', async () => {
        const nested: Block[] = [
            {
                id: 'b_sec',
                type: 'columns',
                attrs: {},
                content: '',
                children: [
                    {
                        id: 'b_col',
                        type: 'column',
                        attrs: {},
                        content: '',
                        children: [
                            {
                                id: 'b_p',
                                type: 'paragraph',
                                attrs: {},
                                content: '<p>Fresh rolls at seven</p>',
                                children: [],
                            },
                        ],
                    },
                ],
            },
        ];
        render(<Harness initial={nested} />);
        await userEvent.click(screen.getByRole('button', { name: 'Outline' }));
        const outline = byTest('outline-view');

        expect(
            within(outline).getByText('Fresh rolls at seven'),
        ).toBeInTheDocument();
        await userEvent.click(
            within(outline).getByRole('button', {
                name: 'Paragraph block moves',
            }),
        );
        await userEvent.click(
            screen.getByRole('menuitem', { name: 'Move out of this group' }),
        );

        expect(latest.map((item) => item.id)).toEqual(['b_sec', 'b_p']);
    });
});

describe('helpers', () => {
    it('keeps the recent list short and unique', () => {
        rememberRecent('block:image', 3);
        rememberRecent('block:quote', 3);
        rememberRecent('block:image', 3);
        rememberRecent('pattern:hero', 3);
        rememberRecent('block:table', 3);

        expect(readRecent()).toEqual([
            'block:table',
            'pattern:hero',
            'block:image',
        ]);
    });

    it('matches every search word', () => {
        const item = {
            key: 'block:paragraph',
            label: 'Paragraph',
            description: 'Plain text with links.',
            icon: Pilcrow,
            category: 'text',
            keywords: ['p'],
            create: () => [],
        };

        expect(filterInsertItems([item], 'plain links')).toHaveLength(1);
        expect(filterInsertItems([item], 'plain video')).toHaveLength(0);
    });

    it('previews block text in the outline', () => {
        expect(
            outlinePreview({
                id: 'x',
                type: 'paragraph',
                attrs: {},
                content: '<p>Open <strong>daily</strong></p>',
                children: [],
            }),
        ).toBe('Open daily');
        expect(
            outlinePreview({
                id: 'y',
                type: 'paragraph',
                attrs: {},
                content: `<p>${'a'.repeat(60)}</p>`,
                children: [],
            }),
        ).toHaveLength(41);
    });
});
