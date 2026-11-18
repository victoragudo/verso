import { beforeEach, describe, expect, it } from 'vitest';
import { builder, canDrop } from '@/builder';
import { registerPluginBlocks } from '@/builder/core';
import { edgeFromPointer, payloadTypes } from '@/builder/dnd';
import { resetBuilder, setupBuilder } from '@/builder/setup';
import {
    insertBlocks,
    isInside,
    keyboardTarget,
    locateBlock,
    moveBlock,
    parentType,
    removeBlock,
} from '@/builder/tree';
import type { Block } from '@/types';

function block(id: string, type: string, children: Block[] = []): Block {
    return { id, type, attrs: {}, content: '', children };
}

// A landing page: hero section with a title and a button, two columns, a closing paragraph.
const page: Block[] = [
    block('hero', 'section', [
        block('title', 'heading'),
        block('cta', 'button'),
    ]),
    block('cols', 'columns', [
        block('left', 'column', [block('menu', 'list')]),
        block('right', 'column', []),
    ]),
    block('outro', 'paragraph'),
];

const ids = (blocks: Block[]): unknown[] =>
    blocks.map((item) =>
        item.children?.length ? { [item.id]: ids(item.children) } : item.id,
    );

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
});

describe('tree helpers', () => {
    it('locates blocks at any depth', () => {
        expect(locateBlock(page, 'menu')).toMatchObject({
            parentId: 'left',
            index: 0,
        });
        expect(locateBlock(page, 'outro')).toMatchObject({
            parentId: null,
            index: 2,
        });
        expect(locateBlock(page, 'nope')).toBeNull();
        expect(parentType(page, 'hero')).toBe('section');
        expect(parentType(page, null)).toBe('root');
    });

    it('knows when a block is inside another', () => {
        expect(isInside(page, 'cols', 'menu')).toBe(true);
        expect(isInside(page, 'hero', 'menu')).toBe(false);
        expect(isInside(page, 'hero', null)).toBe(false);
    });

    it('removes and inserts blocks', () => {
        expect(ids(removeBlock(page, 'cta'))).toEqual([
            { hero: ['title'] },
            { cols: [{ left: ['menu'] }, 'right'] },
            'outro',
        ]);
        expect(
            ids(insertBlocks(page, 'right', 0, [block('photo', 'image')])),
        ).toEqual([
            { hero: ['title', 'cta'] },
            { cols: [{ left: ['menu'] }, { right: ['photo'] }] },
            'outro',
        ]);
        expect(
            ids(insertBlocks(page, null, 99, [block('end', 'separator')])).at(
                -1,
            ),
        ).toBe('end');
    });

    it('moves a block from the page into a column', () => {
        expect(ids(moveBlock(page, 'outro', 'right', 0))).toEqual([
            { hero: ['title', 'cta'] },
            { cols: [{ left: ['menu'] }, { right: ['outro'] }] },
        ]);
    });

    it('moves a block down in the same list, counting the index before removal', () => {
        expect(ids(moveBlock(page, 'title', 'hero', 2))).toEqual([
            { hero: ['cta', 'title'] },
            { cols: [{ left: ['menu'] }, 'right'] },
            'outro',
        ]);
    });

    it('never moves a block into itself and skips moves that change nothing', () => {
        expect(moveBlock(page, 'cols', 'left', 0)).toBe(page);
        expect(moveBlock(page, 'title', 'hero', 1)).toBe(page);
        expect(moveBlock(page, 'title', 'hero', 0)).toBe(page);
    });
});

describe('keyboardTarget', () => {
    it('moves up and down inside a list', () => {
        expect(keyboardTarget(page, 'cta', 'up', canDrop)).toEqual({
            parentId: 'hero',
            index: 0,
        });
        expect(keyboardTarget(page, 'title', 'up', canDrop)).toBeNull();
        expect(keyboardTarget(page, 'title', 'down', canDrop)).toEqual({
            parentId: 'hero',
            index: 2,
        });
        expect(keyboardTarget(page, 'outro', 'down', canDrop)).toBeNull();
    });

    it('moves into the block above, using the last column of columns', () => {
        expect(keyboardTarget(page, 'cols', 'into', canDrop)).toEqual({
            parentId: 'hero',
            index: 2,
        });
        expect(keyboardTarget(page, 'outro', 'into', canDrop)).toEqual({
            parentId: 'right',
            index: 0,
        });
        expect(keyboardTarget(page, 'hero', 'into', canDrop)).toBeNull();
    });

    it('moves out of a section, and out of columns from a column', () => {
        expect(keyboardTarget(page, 'cta', 'out', canDrop)).toEqual({
            parentId: null,
            index: 1,
        });
        expect(keyboardTarget(page, 'menu', 'out', canDrop)).toEqual({
            parentId: null,
            index: 2,
        });
        expect(keyboardTarget(page, 'outro', 'out', canDrop)).toBeNull();
    });

    it('refuses moves the drop rules forbid', () => {
        const withColumnsAbove = [
            block('a', 'columns', [block('a1', 'column')]),
            block('b', 'columns', [block('b1', 'column')]),
        ];

        expect(
            keyboardTarget(withColumnsAbove, 'b', 'into', canDrop),
        ).toBeNull();
    });
});

describe('drag helpers', () => {
    it('finds the edge from the pointer', () => {
        const rect = { top: 100, height: 40 };

        expect(edgeFromPointer(105, rect, false)).toBe('before');
        expect(edgeFromPointer(135, rect, false)).toBe('after');
        expect(edgeFromPointer(120, rect, true)).toBe('inside');
        expect(edgeFromPointer(102, rect, true)).toBe('before');
    });

    it('lists the types a drag would add', () => {
        expect(
            payloadTypes({ kind: 'move', id: 'title', type: 'heading' }),
        ).toEqual(['heading']);
        expect(
            payloadTypes({
                kind: 'insert',
                key: 'pattern:hero',
                blocks: [block('x', 'heading'), block('y', 'button')],
            }),
        ).toEqual(['heading', 'button']);
    });
});
