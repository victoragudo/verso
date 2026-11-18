import { describe, expect, it } from 'vitest';
import {
    groupPatterns,
    insertBlocksAfter,
    patternToBlocks,
    searchPatterns,
} from '@/components/verso/editor/patterns';
import type { Block, BlockPattern } from '@/types';

const callToAction: BlockPattern = {
    name: 'call-to-action',
    title: 'Call to action',
    category: 'Call to action',
    description: 'A short heading, one line of text and a button.',
    keywords: ['signup', 'banner'],
    blocks: [
        { type: 'heading', attrs: { level: 2 }, content: 'Plan your trip' },
        {
            type: 'button',
            attrs: { label: 'Read the guides', url: '/blog' },
        },
    ],
};

const twoColumns: BlockPattern = {
    name: 'two-columns',
    title: 'Two columns with headings',
    category: 'Layout',
    description: '',
    keywords: ['side by side'],
    blocks: [
        {
            type: 'columns',
            attrs: {},
            children: [
                {
                    type: 'column',
                    children: [
                        {
                            type: 'paragraph',
                            content: '<p>Getting there</p>',
                        },
                    ],
                },
            ],
        },
    ],
};

const openingHours: BlockPattern = {
    name: 'opening-hours',
    title: 'Opening hours',
    category: 'Call to action',
    description: 'Café opening times.',
    keywords: [],
    blocks: [{ type: 'paragraph', content: '<p>Open daily.</p>' }],
};

function paragraph(id: string): Block {
    return { id, type: 'paragraph', attrs: {}, content: '<p>Harbor</p>' };
}

describe('patternToBlocks', () => {
    it('gives every block, nested ones too, a new unique id', () => {
        const blocks = patternToBlocks(twoColumns);
        const column = blocks[0].children?.[0];
        const ids = [blocks[0].id, column?.id, column?.children?.[0].id];

        expect(new Set(ids).size).toBe(3);
        ids.forEach((id) => expect(id).toMatch(/^b_/));
        expect(column?.type).toBe('column');
        expect(column?.attrs).toEqual({});
        expect(column?.children?.[0].content).toBe('<p>Getting there</p>');
    });

    it('copies attrs so editing a block never changes the pattern', () => {
        const blocks = patternToBlocks(callToAction);
        blocks[0].attrs.level = 4;

        expect(callToAction.blocks[0].attrs).toEqual({ level: 2 });
    });

    it('gives new ids on every insert', () => {
        expect(patternToBlocks(callToAction)[0].id).not.toBe(
            patternToBlocks(callToAction)[0].id,
        );
    });

    it('leaves out content and children when the pattern has none', () => {
        const [button] = patternToBlocks(callToAction).slice(1);

        expect(button).not.toHaveProperty('content');
        expect(button).not.toHaveProperty('children');
    });
});

describe('insertBlocksAfter', () => {
    const blocks = [paragraph('b_one'), paragraph('b_two')];
    const added = [paragraph('b_new1'), paragraph('b_new2')];

    it('adds the blocks after the given block', () => {
        expect(
            insertBlocksAfter(blocks, 'b_one', added).map((item) => item.id),
        ).toEqual(['b_one', 'b_new1', 'b_new2', 'b_two']);
    });

    it('adds the blocks at the end without a cursor or for an unknown id', () => {
        expect(
            insertBlocksAfter(blocks, null, added).map((item) => item.id),
        ).toEqual(['b_one', 'b_two', 'b_new1', 'b_new2']);
        expect(
            insertBlocksAfter(blocks, 'b_gone', added).map((item) => item.id),
        ).toEqual(['b_one', 'b_two', 'b_new1', 'b_new2']);
    });

    it('does not change the original list', () => {
        insertBlocksAfter(blocks, 'b_one', added);

        expect(blocks).toHaveLength(2);
    });
});

describe('searchPatterns', () => {
    const all = [callToAction, twoColumns, openingHours];

    it('returns every pattern for an empty query', () => {
        expect(searchPatterns(all, '  ')).toEqual(all);
    });

    it('matches titles, categories, descriptions, names and keywords', () => {
        const names = (query: string) =>
            searchPatterns(all, query).map((pattern) => pattern.name);

        expect(names('columns')).toEqual(['two-columns']);
        expect(names('layout')).toEqual(['two-columns']);
        expect(names('button')).toEqual(['call-to-action']);
        expect(names('signup')).toEqual(['call-to-action']);
        expect(names('side by')).toEqual(['two-columns']);
        expect(names('cafe')).toEqual(['opening-hours']);
        expect(names('call action')).toEqual([
            'call-to-action',
            'opening-hours',
        ]);
        expect(names('spreadsheet')).toEqual([]);
    });
});

describe('groupPatterns', () => {
    it('groups by category in first order', () => {
        const groups = groupPatterns([callToAction, twoColumns, openingHours]);

        expect(groups.map((group) => group.category)).toEqual([
            'Call to action',
            'Layout',
        ]);
        expect(groups[0].patterns.map((pattern) => pattern.name)).toEqual([
            'call-to-action',
            'opening-hours',
        ]);
    });
});
