import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { BlockEditor } from '@/components/verso/editor/block-editor';
import { PatternPicker } from '@/components/verso/editor/pattern-picker';
import type { Block, BlockPattern } from '@/types';

const patterns: BlockPattern[] = [
    {
        name: 'call-to-action',
        title: 'Call to action',
        category: 'Call to action',
        description: 'A short heading, one line of text and a button.',
        keywords: ['signup'],
        blocks: [
            { type: 'heading', attrs: { level: 2 }, content: 'Book a tour' },
            {
                type: 'paragraph',
                attrs: {},
                content: '<p>Boats leave at nine.</p>',
            },
        ],
    },
    {
        name: 'pull-quote',
        title: 'Pull quote between separators',
        category: 'Text',
        description: '',
        keywords: ['testimonial'],
        blocks: [{ type: 'separator', attrs: {} }],
    },
];

function ControlledEditor({
    initial,
    onChange,
    withPatterns = patterns,
}: {
    initial: Block[];
    onChange: (blocks: Block[]) => void;
    withPatterns?: BlockPattern[];
}) {
    const [blocks, setBlocks] = useState(initial);

    return (
        <BlockEditor
            value={blocks}
            patterns={withPatterns}
            onChange={(next) => {
                setBlocks(next);
                onChange(next);
            }}
        />
    );
}

describe('PatternPicker', () => {
    it('lists patterns grouped by category', async () => {
        render(<PatternPicker patterns={patterns} onInsert={vi.fn()} />);

        await userEvent.click(screen.getByRole('button', { name: 'Patterns' }));

        const callGroup = screen.getByRole('region', {
            name: 'Call to action',
        });

        expect(
            within(callGroup).getByRole('button', { name: /Call to action/ }),
        ).toBeInTheDocument();
        expect(
            within(screen.getByRole('region', { name: 'Text' })).getByText(
                'Pull quote between separators',
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByText('A short heading, one line of text and a button.'),
        ).toBeInTheDocument();
    });

    it('filters the list with the search box', async () => {
        render(<PatternPicker patterns={patterns} onInsert={vi.fn()} />);

        await userEvent.click(screen.getByRole('button', { name: 'Patterns' }));
        await userEvent.type(
            screen.getByRole('searchbox', { name: 'Search patterns' }),
            'testimonial',
        );

        expect(
            screen.getByText('Pull quote between separators'),
        ).toBeInTheDocument();
        expect(
            screen.queryByText('Call to action', { selector: 'span' }),
        ).toBeNull();
    });

    it('says when no pattern matches', async () => {
        render(<PatternPicker patterns={patterns} onInsert={vi.fn()} />);

        await userEvent.click(screen.getByRole('button', { name: 'Patterns' }));
        await userEvent.type(
            screen.getByRole('searchbox', { name: 'Search patterns' }),
            'carousel',
        );

        expect(screen.getByText('No pattern matches.')).toBeInTheDocument();
    });

    it('inserts the clicked pattern and closes', async () => {
        const onInsert = vi.fn();
        render(<PatternPicker patterns={patterns} onInsert={onInsert} />);

        await userEvent.click(screen.getByRole('button', { name: 'Patterns' }));
        await userEvent.click(
            screen.getByRole('button', {
                name: /Pull quote between separators/,
            }),
        );

        expect(onInsert).toHaveBeenCalledWith(patterns[1]);
        expect(
            screen.queryByRole('searchbox', { name: 'Search patterns' }),
        ).toBeNull();
    });

    it('inserts the first match with Enter', async () => {
        const onInsert = vi.fn();
        render(<PatternPicker patterns={patterns} onInsert={onInsert} />);

        await userEvent.click(screen.getByRole('button', { name: 'Patterns' }));
        await userEvent.type(
            screen.getByRole('searchbox', { name: 'Search patterns' }),
            'signup{Enter}',
        );

        expect(onInsert).toHaveBeenCalledWith(patterns[0]);
    });
});

describe('BlockEditor patterns', () => {
    it('shows the Patterns button only when the theme has patterns', () => {
        const { unmount } = render(
            <ControlledEditor
                initial={[]}
                onChange={vi.fn()}
                withPatterns={[]}
            />,
        );

        expect(screen.queryByRole('button', { name: 'Patterns' })).toBeNull();
        unmount();

        render(<ControlledEditor initial={[]} onChange={vi.fn()} />);

        expect(
            screen.getByRole('button', { name: 'Patterns' }),
        ).toBeInTheDocument();
    });

    it('adds the pattern blocks with new ids at the end', async () => {
        const onChange = vi.fn<(blocks: Block[]) => void>();
        render(
            <ControlledEditor
                initial={[
                    {
                        id: 'b_intro',
                        type: 'separator',
                        attrs: {},
                    },
                ]}
                onChange={onChange}
            />,
        );

        await userEvent.click(screen.getByRole('button', { name: 'Patterns' }));
        await userEvent.click(
            screen.getByRole('button', { name: /Call to action/ }),
        );

        const blocks = onChange.mock.lastCall?.[0] ?? [];

        expect(blocks.map((block) => block.type)).toEqual([
            'separator',
            'heading',
            'paragraph',
        ]);
        expect(blocks[1]).toMatchObject({
            attrs: { level: 2 },
            content: 'Book a tour',
        });
        expect(blocks[1].id).not.toBe(blocks[2].id);
        expect(blocks[1].id).toMatch(/^b_/);
    });
});
