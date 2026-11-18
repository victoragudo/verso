import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { BlockEditor } from '@/components/verso/editor/block-editor';
import type { Block, PluginBlockDefinition } from '@/types';

const recipeCard: PluginBlockDefinition = {
    name: 'recipe-card',
    label: 'Recipe card',
    description: 'Ingredients, steps and baking time.',
    children: false,
    fields: [
        {
            name: 'servings',
            label: 'Servings',
            type: 'number',
            default: 4,
            options: [],
            help: '',
            required: true,
            min: 1,
            max: 24,
        },
    ],
};

function paragraph(id: string, html: string): Block {
    return { id, type: 'paragraph', attrs: {}, content: html, children: [] };
}

// Keeps the blocks in state, like the entry form does, and reports each change.
function ControlledEditor({
    initial,
    onChange,
    pluginBlocks,
}: {
    initial: Block[];
    onChange: (blocks: Block[]) => void;
    pluginBlocks?: PluginBlockDefinition[];
}) {
    const [blocks, setBlocks] = useState(initial);

    return (
        <BlockEditor
            value={blocks}
            pluginBlocks={pluginBlocks}
            onChange={(next) => {
                setBlocks(next);
                onChange(next);
            }}
        />
    );
}

function renderEditor(
    initial: Block[],
    pluginBlocks?: PluginBlockDefinition[],
) {
    const onChange = vi.fn<(blocks: Block[]) => void>();

    render(
        <ControlledEditor
            initial={initial}
            onChange={onChange}
            pluginBlocks={pluginBlocks}
        />,
    );

    return { onChange, last: () => onChange.mock.lastCall?.[0] ?? [] };
}

const optionNames = () =>
    within(screen.getByRole('listbox', { name: 'Insert a block' }))
        .getAllByRole('option')
        .map((option) => option.querySelector('span span')?.textContent);

describe('BlockEditor', () => {
    it('offers a start button when there are no blocks', async () => {
        const { last } = renderEditor([]);

        await userEvent.click(
            screen.getByRole('button', {
                name: 'Start writing, or type / to choose a block',
            }),
        );

        expect(last()).toHaveLength(1);
        expect(last()[0]).toMatchObject({
            type: 'paragraph',
            content: '<p></p>',
        });
    });

    it('renders one text box per text block', async () => {
        renderEditor([
            paragraph('b_p1', '<p>Spring menu</p>'),
            paragraph('b_p2', '<p>Lemon tart is back</p>'),
        ]);

        await waitFor(() =>
            expect(
                screen.getAllByRole('textbox', { name: 'Paragraph' }),
            ).toHaveLength(2),
        );
    });

    it('shows the slash menu with every block for a single slash', () => {
        renderEditor([paragraph('b_p1', '<p>/</p>')]);

        expect(optionNames()).toContain('Paragraph');
        expect(optionNames()).toContain('HTML');
        expect(optionNames()).not.toContain('Column');
    });

    it('filters the slash menu by the typed command', () => {
        renderEditor([paragraph('b_p1', '<p>/ima</p>')]);

        expect(optionNames()).toEqual(['Image']);
    });

    it('marks the first match as active', () => {
        renderEditor([paragraph('b_p1', '<p>/grid</p>')]);

        const options = screen.getAllByRole('option');

        expect(options[0]).toHaveAttribute('aria-selected', 'true');
        expect(options[1]).toHaveAttribute('aria-selected', 'false');
    });

    it('shows a message when nothing matches', () => {
        renderEditor([paragraph('b_p1', '<p>/spreadsheet</p>')]);

        expect(screen.getByText('No matching blocks')).toBeInTheDocument();
    });

    it('does not open the slash menu for normal text', () => {
        renderEditor([paragraph('b_p1', '<p>Prices from 3/4 of a loaf</p>')]);

        expect(
            screen.queryByRole('listbox', { name: 'Insert a block' }),
        ).not.toBeInTheDocument();
    });

    it('does not open the slash menu once a space follows the command', () => {
        renderEditor([paragraph('b_p1', '<p>/image of the shop</p>')]);

        expect(
            screen.queryByRole('listbox', { name: 'Insert a block' }),
        ).not.toBeInTheDocument();
    });

    it('does not open the slash menu in a heading', () => {
        renderEditor([
            {
                id: 'b_h1',
                type: 'heading',
                attrs: { level: 2 },
                content: '/ima',
                children: [],
            },
        ]);

        expect(
            screen.queryByRole('listbox', { name: 'Insert a block' }),
        ).not.toBeInTheDocument();
    });

    it('replaces the paragraph with the chosen block', async () => {
        const { last } = renderEditor([
            paragraph('b_intro', '<p>Welcome</p>'),
            paragraph('b_slash', '<p>/quo</p>'),
        ]);

        await userEvent.click(screen.getByRole('option', { name: /Quote/ }));

        expect(last()).toHaveLength(2);
        expect(last()[0].id).toBe('b_intro');
        expect(last()[1]).toMatchObject({
            type: 'quote',
            attrs: { citation: '' },
        });
        expect(last()[1].id).not.toBe('b_slash');
    });

    it('lists plugin blocks in the slash menu', () => {
        renderEditor([paragraph('b_p1', '<p>/recipe</p>')], [recipeCard]);

        expect(optionNames()).toEqual(['Recipe card']);
    });

    it('creates a plugin block with its defaults and shows its form', async () => {
        const { last } = renderEditor(
            [paragraph('b_p1', '<p>/recipe</p>')],
            [recipeCard],
        );

        await userEvent.click(screen.getByRole('option', { name: /Recipe/ }));

        expect(last()[0]).toMatchObject({
            type: 'recipe-card',
            attrs: { servings: 4 },
        });
        expect(screen.getByLabelText(/Servings/)).toHaveValue(4);
    });

    it('appends a block from the insert toolbar', async () => {
        const { last } = renderEditor([paragraph('b_p1', '<p>Hello</p>')]);

        await userEvent.click(
            screen.getByRole('button', { name: 'Insert quote' }),
        );

        expect(last().map((block) => block.type)).toEqual([
            'paragraph',
            'quote',
        ]);
    });

    it('appends a less common block from the more blocks menu', async () => {
        const { last } = renderEditor([paragraph('b_p1', '<p>Hello</p>')]);

        await userEvent.click(
            screen.getByRole('button', { name: 'More blocks' }),
        );
        await userEvent.click(
            await screen.findByRole('menuitem', { name: 'Separator' }),
        );

        expect(last().map((block) => block.type)).toEqual([
            'paragraph',
            'separator',
        ]);
    });

    it('groups the editor controls in one labelled toolbar', () => {
        renderEditor([paragraph('b_p1', '<p>Hello</p>')]);

        const toolbar = screen.getByRole('toolbar', { name: 'Editor' });

        expect(
            within(toolbar).getByRole('group', { name: 'Text formatting' }),
        ).toBeInTheDocument();
        expect(
            within(toolbar).getByRole('group', { name: 'Insert block' }),
        ).toBeInTheDocument();
        expect(
            within(toolbar).queryByRole('button', {
                name: 'Insert separator',
            }),
        ).not.toBeInTheDocument();
    });

    it('shows a note for block types it can not edit', () => {
        renderEditor([
            {
                id: 'b_x',
                type: 'countdown-timer',
                attrs: {},
                content: '',
                children: [],
            },
        ]);

        expect(
            screen.getByText('This block type can not be edited here.'),
        ).toBeInTheDocument();
    });

    it('moves a block down with the block menu', async () => {
        const { last } = renderEditor([
            paragraph('b_first', '<p>First</p>'),
            paragraph('b_second', '<p>Second</p>'),
        ]);

        await userEvent.click(
            screen.getAllByRole('button', {
                name: 'Paragraph block options',
            })[0],
        );
        await userEvent.click(
            await screen.findByRole('menuitem', { name: /Move down/ }),
        );

        expect(last().map((block) => block.id)).toEqual([
            'b_second',
            'b_first',
        ]);
    });

    it('duplicates a block right after itself with a new id', async () => {
        const { last } = renderEditor([
            paragraph('b_first', '<p>Sourdough</p>'),
            paragraph('b_second', '<p>Rye</p>'),
        ]);

        await userEvent.click(
            screen.getAllByRole('button', {
                name: 'Paragraph block options',
            })[0],
        );
        await userEvent.click(
            await screen.findByRole('menuitem', { name: /Duplicate/ }),
        );

        expect(last()).toHaveLength(3);
        expect(last()[1].content).toBe('<p>Sourdough</p>');
        expect(last()[1].id).not.toBe('b_first');
        expect(last()[2].id).toBe('b_second');
    });

    it('deletes a block', async () => {
        const { last } = renderEditor([
            paragraph('b_first', '<p>Keep me</p>'),
            paragraph('b_second', '<p>Remove me</p>'),
        ]);

        await userEvent.click(
            screen.getAllByRole('button', {
                name: 'Paragraph block options',
            })[1],
        );
        await userEvent.click(
            await screen.findByRole('menuitem', { name: /Delete/ }),
        );

        expect(last().map((block) => block.id)).toEqual(['b_first']);
    });

    it('adds a column to a columns block', async () => {
        const { last } = renderEditor([
            {
                id: 'b_cols',
                type: 'columns',
                attrs: {},
                content: '',
                children: [
                    {
                        id: 'b_col1',
                        type: 'column',
                        attrs: {},
                        content: '',
                        children: [],
                    },
                ],
            },
        ]);

        await userEvent.click(screen.getByRole('button', { name: 'Column' }));

        expect(last()[0].children).toHaveLength(2);
        expect(last()[0].children?.[1].type).toBe('column');
    });
});

describe('BlockEditor columns', () => {
    const columns = (count: number): Block => ({
        id: 'b_cols',
        type: 'columns',
        attrs: {},
        content: '',
        children: Array.from({ length: count }, (_, index) => ({
            id: `b_col${index}`,
            type: 'column',
            attrs: {},
            content: '',
            children: [],
        })),
    });

    it('stops adding columns at four', () => {
        renderEditor([columns(4)]);

        expect(screen.getByRole('button', { name: 'Column' })).toBeDisabled();
    });

    it('keeps at least one column', () => {
        renderEditor([columns(1)]);

        expect(
            screen.getByRole('button', { name: 'Remove last column' }),
        ).toBeDisabled();
    });

    it('removes the last column', async () => {
        const { last } = renderEditor([columns(3)]);

        await userEvent.click(
            screen.getByRole('button', { name: 'Remove last column' }),
        );

        expect(last()[0].children?.map((column) => column.id)).toEqual([
            'b_col0',
            'b_col1',
        ]);
    });

    it('does not offer columns inside a column', async () => {
        renderEditor([columns(1)]);

        await userEvent.click(screen.getByRole('button', { name: 'Add' }));

        expect(
            await screen.findByRole('menuitem', { name: 'Paragraph' }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('menuitem', { name: 'Columns' }),
        ).not.toBeInTheDocument();
    });

    it('adds a block inside a column', async () => {
        const { last } = renderEditor([columns(2)]);

        await userEvent.click(
            screen.getAllByRole('button', { name: 'Add' })[1],
        );
        await userEvent.click(
            await screen.findByRole('menuitem', { name: 'Button' }),
        );

        expect(last()[0].children?.[0].children).toEqual([]);
        expect(last()[0].children?.[1].children?.[0].type).toBe('button');
    });
});
