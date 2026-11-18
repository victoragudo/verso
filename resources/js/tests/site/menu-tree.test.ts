import { describe, expect, it } from 'vitest';
import {
    canIndent,
    canMoveDown,
    canMoveUp,
    flatten,
    indent,
    moveBlock,
    moveDown,
    moveUp,
    outdent,
    remove,
    toTree,
} from '@/components/verso/site/menu-tree';
import type { FlatMenuItem } from '@/components/verso/site/menu-tree';
import type { MenuItemNode } from '@/types';

function node(title: string, children: MenuItemNode[] = []): MenuItemNode {
    return {
        id: null,
        type: 'entry',
        entry_id: null,
        term_id: null,
        label: null,
        url: null,
        open_in_new_tab: false,
        mega: false,
        rel: [],
        visibility: 'everyone',
        title,
        visible: true,
        children,
    };
}

// About, Contact (with Opening Hours and Directions), Privacy Policy.
function footerMenu(): FlatMenuItem[] {
    return flatten([
        node('About'),
        node('Contact', [node('Opening Hours'), node('Directions')]),
        node('Privacy Policy'),
    ]);
}

const titles = (items: FlatMenuItem[]) =>
    items.map((item) => `${item.depth}:${item.title}`);

describe('canMoveUp and canMoveDown', () => {
    it('blocks moving the first top level item up', () => {
        const items = footerMenu();

        expect(canMoveUp(items, 0)).toBe(false);
        expect(canMoveDown(items, 0)).toBe(true);
    });

    it('blocks moving the last top level item down', () => {
        const items = footerMenu();
        const last = items.length - 1;

        expect(canMoveDown(items, last)).toBe(false);
        expect(canMoveUp(items, last)).toBe(true);
    });

    it('only allows moves between siblings of the same parent', () => {
        const items = footerMenu();

        expect(canMoveUp(items, 2)).toBe(false);
        expect(canMoveDown(items, 2)).toBe(true);
        expect(canMoveUp(items, 3)).toBe(true);
        expect(canMoveDown(items, 3)).toBe(false);
    });

    it('agrees with what moveUp and moveDown really do', () => {
        const items = footerMenu();

        items.forEach((_item, index) => {
            expect(moveUp(items, index) !== items).toBe(
                canMoveUp(items, index),
            );
            expect(moveDown(items, index) !== items).toBe(
                canMoveDown(items, index),
            );
        });
    });

    it('treats a single item as fixed in place', () => {
        const items = flatten([node('Shop')]);

        expect(canMoveUp(items, 0)).toBe(false);
        expect(canMoveDown(items, 0)).toBe(false);
    });
});

describe('moving items', () => {
    it('moves an item with its sub items above the previous sibling', () => {
        expect(titles(moveUp(footerMenu(), 1))).toEqual([
            '0:Contact',
            '1:Opening Hours',
            '1:Directions',
            '0:About',
            '0:Privacy Policy',
        ]);
    });

    it('moves an item with its sub items below the next sibling', () => {
        expect(titles(moveDown(footerMenu(), 1))).toEqual([
            '0:About',
            '0:Privacy Policy',
            '0:Contact',
            '1:Opening Hours',
            '1:Directions',
        ]);
    });
});

describe('nesting items', () => {
    it('nests an item under the item above and back out again', () => {
        const nested = indent(footerMenu(), 4, 3);

        expect(titles(nested)[4]).toBe('1:Privacy Policy');
        expect(titles(outdent(nested, 4))).toEqual(titles(footerMenu()));
    });

    it('does not nest the first item or go past the depth limit', () => {
        const items = footerMenu();

        expect(canIndent(items, 0, 3)).toBe(false);
        expect(canIndent(items, 1, 2)).toBe(false);
        expect(canIndent(items, 3, 3)).toBe(true);
    });

    it('removes an item together with its sub items', () => {
        expect(titles(remove(footerMenu(), 1))).toEqual([
            '0:About',
            '0:Privacy Policy',
        ]);
    });

    it('turns the flat list back into the same tree', () => {
        const tree = toTree(footerMenu());

        expect(tree.map((item) => item.title)).toEqual([
            'About',
            'Contact',
            'Privacy Policy',
        ]);
        expect(tree[1].children.map((item) => item.title)).toEqual([
            'Opening Hours',
            'Directions',
        ]);
    });
});

describe('moveBlock', () => {
    const titles = (items: FlatMenuItem[]) =>
        items.map((item) => `${item.depth}:${item.title}`);

    it('drops an item with its sub items before another item', () => {
        expect(titles(moveBlock(footerMenu(), 1, 0, 3))).toEqual([
            '0:Contact',
            '1:Opening Hours',
            '1:Directions',
            '0:About',
            '0:Privacy Policy',
        ]);
    });

    it('drops an item at the end of the list at the top level', () => {
        expect(titles(moveBlock(footerMenu(), 2, 5, 3))).toEqual([
            '0:About',
            '0:Contact',
            '1:Directions',
            '0:Privacy Policy',
            '0:Opening Hours',
        ]);
    });

    it('takes the level of the item it lands before', () => {
        expect(titles(moveBlock(footerMenu(), 4, 3, 3))).toEqual([
            '0:About',
            '0:Contact',
            '1:Opening Hours',
            '1:Privacy Policy',
            '1:Directions',
        ]);
    });

    it('ignores drops inside the item itself or that make the menu too deep', () => {
        const items = footerMenu();

        expect(moveBlock(items, 1, 2, 3)).toBe(items);
        expect(moveBlock(items, 1, 3, 3)).toBe(items);
        expect(moveBlock(items, 1, 2, 1)).toBe(items);
        expect(moveBlock(items, 1, 3, 1)).toBe(items);
    });
});
