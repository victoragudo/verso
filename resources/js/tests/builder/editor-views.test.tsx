import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { resetBuilder, setupBuilder } from '@/builder/setup';
import {
    BlockBreadcrumb,
    blockPath,
} from '@/components/verso/editor/block-breadcrumb';
import { comparisonDocument } from '@/components/verso/content/revision-compare';
import type { Block } from '@/types';

const page: Block[] = [
    {
        id: 'b_hero',
        type: 'section',
        attrs: {},
        content: '',
        children: [
            {
                id: 'b_cols',
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
                                id: 'b_text',
                                type: 'paragraph',
                                attrs: {},
                                content: 'Fresh bread',
                                children: [],
                            },
                        ],
                    },
                ],
            },
        ],
    },
];

beforeEach(() => {
    resetBuilder();
    setupBuilder({});
});

describe('BlockBreadcrumb', () => {
    it('finds the path to a nested block', () => {
        expect(blockPath(page, 'b_text').map((block) => block.id)).toEqual([
            'b_hero',
            'b_cols',
            'b_col',
            'b_text',
        ]);
        expect(blockPath(page, 'missing')).toEqual([]);
    });

    it('selects a parent block from the trail', () => {
        const onPick = vi.fn();
        render(
            <BlockBreadcrumb
                blocks={page}
                selectedId="b_text"
                onPick={onPick}
            />,
        );

        expect(
            screen.getByRole('navigation', { name: 'Selected block' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Paragraph' }),
        ).toHaveAttribute('aria-current', 'location');

        fireEvent.click(screen.getByRole('button', { name: 'Columns' }));
        expect(onPick).toHaveBeenCalledWith('b_cols');
    });

    it('shows nothing without a selected block', () => {
        const { container } = render(
            <BlockBreadcrumb
                blocks={page}
                selectedId={null}
                onPick={vi.fn()}
            />,
        );

        expect(container).toBeEmptyDOMElement();
    });
});

describe('comparisonDocument', () => {
    it('marks the changed blocks of each side with a label and a line style', () => {
        const changes = [
            {
                id: 'b_new',
                type: 'paragraph',
                change: 'added' as const,
                text: 'New',
            },
            {
                id: 'b_old',
                type: 'paragraph',
                change: 'removed' as const,
                text: 'Old',
            },
        ];
        const before = comparisonDocument(
            '<p data-verso-block="b_old">Old</p>',
            changes,
            'before',
        );
        const after = comparisonDocument(
            '<p data-verso-block="b_new">New</p>',
            changes,
            'after',
        );

        expect(before).toContain(
            '[data-verso-block="b_old"]{outline:3px dashed',
        );
        expect(before).not.toContain('b_new');
        expect(after).toContain('content:"Added"');
        expect(after).not.toContain('b_old');
    });
});
