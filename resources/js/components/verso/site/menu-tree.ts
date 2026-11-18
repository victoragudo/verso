import type { MenuItemNode } from '@/types';

// A menu item in the builder: the tree is kept as a flat list with a depth.
export type FlatMenuItem = Omit<MenuItemNode, 'children'> & {
    key: string;
    depth: number;
};

let nextKey = 0;

export function newKey(): string {
    nextKey += 1;

    return `item-${nextKey}`;
}

// Turns the nested items from the server into a flat list.
export function flatten(nodes: MenuItemNode[], depth = 0): FlatMenuItem[] {
    return nodes.flatMap(({ children, ...node }) => [
        { ...node, key: newKey(), depth },
        ...flatten(children, depth + 1),
    ]);
}

// Turns the flat list back into nested items for saving.
export function toTree(items: FlatMenuItem[]): MenuItemNode[] {
    const roots: MenuItemNode[] = [];
    const stack: MenuItemNode[] = [];

    for (const { key: _key, depth, ...item } of items) {
        const node: MenuItemNode = { ...item, children: [] };
        stack.length = Math.min(depth, stack.length);
        const parent = stack[stack.length - 1];
        (parent ? parent.children : roots).push(node);
        stack.push(node);
    }

    return roots;
}

// Index just after the item and all its sub items.
export function blockEnd(items: FlatMenuItem[], index: number): number {
    let end = index + 1;

    while (end < items.length && items[end].depth > items[index].depth) {
        end += 1;
    }

    return end;
}

function previousSibling(items: FlatMenuItem[], index: number): number {
    const depth = items[index].depth;

    for (let i = index - 1; i >= 0; i -= 1) {
        if (items[i].depth < depth) {
            return -1;
        }

        if (items[i].depth === depth) {
            return i;
        }
    }

    return -1;
}

function nextSibling(items: FlatMenuItem[], index: number): number {
    const end = blockEnd(items, index);

    return end < items.length && items[end].depth === items[index].depth
        ? end
        : -1;
}

// True when the item has a sibling above it to swap with.
export function canMoveUp(items: FlatMenuItem[], index: number): boolean {
    return previousSibling(items, index) >= 0;
}

// True when the item has a sibling below it to swap with.
export function canMoveDown(items: FlatMenuItem[], index: number): boolean {
    return nextSibling(items, index) >= 0;
}

// Moves an item, with its sub items, above its previous sibling.
export function moveUp(items: FlatMenuItem[], index: number): FlatMenuItem[] {
    const target = previousSibling(items, index);

    if (target < 0) {
        return items;
    }

    const end = blockEnd(items, index);

    return [
        ...items.slice(0, target),
        ...items.slice(index, end),
        ...items.slice(target, index),
        ...items.slice(end),
    ];
}

// Moves an item, with its sub items, below its next sibling.
export function moveDown(items: FlatMenuItem[], index: number): FlatMenuItem[] {
    const next = nextSibling(items, index);

    return next < 0 ? items : moveUp(items, next);
}

// Deepest level inside the block of an item, counted from the item.
function blockHeight(items: FlatMenuItem[], index: number): number {
    const end = blockEnd(items, index);
    let height = 1;

    for (let i = index; i < end; i += 1) {
        height = Math.max(height, items[i].depth - items[index].depth + 1);
    }

    return height;
}

export function canIndent(
    items: FlatMenuItem[],
    index: number,
    maxDepth: number,
): boolean {
    return (
        previousSibling(items, index) >= 0 &&
        items[index].depth + blockHeight(items, index) < maxDepth
    );
}

// Makes an item a sub item of its previous sibling.
export function indent(
    items: FlatMenuItem[],
    index: number,
    maxDepth: number,
): FlatMenuItem[] {
    if (!canIndent(items, index, maxDepth)) {
        return items;
    }

    return shiftBlock(items, index, 1);
}

// Moves an item one level up, after its parent.
export function outdent(items: FlatMenuItem[], index: number): FlatMenuItem[] {
    if (items[index].depth === 0) {
        return items;
    }

    const end = blockEnd(items, index);
    const block = items
        .slice(index, end)
        .map((item) => ({ ...item, depth: item.depth - 1 }));
    const rest = [...items.slice(0, index), ...items.slice(end)];
    let insertAt = index;

    while (insertAt < rest.length && rest[insertAt].depth > block[0].depth) {
        insertAt += 1;
    }

    return [...rest.slice(0, insertAt), ...block, ...rest.slice(insertAt)];
}

// Removes an item and all its sub items.
export function remove(items: FlatMenuItem[], index: number): FlatMenuItem[] {
    return [...items.slice(0, index), ...items.slice(blockEnd(items, index))];
}

function shiftBlock(
    items: FlatMenuItem[],
    index: number,
    by: number,
): FlatMenuItem[] {
    const end = blockEnd(items, index);

    return items.map((item, i) =>
        i >= index && i < end ? { ...item, depth: item.depth + by } : item,
    );
}

// Moves an item, with its sub items, to just before the item at "to" (or to the end).
// The moved items take the level of the item they land before, or the top level at the end.
// Returns the same list when the move lands inside the item itself or makes the menu too deep.
export function moveBlock(
    items: FlatMenuItem[],
    from: number,
    to: number,
    maxDepth: number,
): FlatMenuItem[] {
    const end = blockEnd(items, from);

    if (to >= from && to <= end) {
        return items;
    }

    const depth = to < items.length ? items[to].depth : 0;

    if (depth + blockHeight(items, from) > maxDepth) {
        return items;
    }

    const shift = depth - items[from].depth;
    const block = items
        .slice(from, end)
        .map((item) => ({ ...item, depth: item.depth + shift }));
    const rest = [...items.slice(0, from), ...items.slice(end)];
    const at = to > from ? to - block.length : to;

    return [...rest.slice(0, at), ...block, ...rest.slice(at)];
}
