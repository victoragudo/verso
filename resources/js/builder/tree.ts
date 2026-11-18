import type { Block } from '@/types';

// Finds a block anywhere in the tree by its id.
export function findBlock(blocks: Block[], id: string | null): Block | null {
    if (id === null) {
        return null;
    }

    for (const block of blocks) {
        if (block.id === id) {
            return block;
        }

        const child = findBlock(block.children ?? [], id);

        if (child) {
            return child;
        }
    }

    return null;
}

// A copy of the tree with one block replaced. Untouched branches keep their objects.
export function replaceBlock(
    blocks: Block[],
    id: string,
    next: Block,
): Block[] {
    let changed = false;
    const result = blocks.map((block) => {
        if (block.id === id) {
            changed = true;

            return next;
        }

        if (!block.children?.length) {
            return block;
        }

        const children = replaceBlock(block.children, id, next);

        if (children === block.children) {
            return block;
        }

        changed = true;

        return { ...block, children };
    });

    return changed ? result : blocks;
}

// Type name used for the page itself when it is the parent.
export const ROOT = 'root';

export type BlockPlace = {
    parentId: string | null;
    index: number;
    siblings: Block[];
};

// Where a block lives: its parent (null for the page) and its position.
export function locateBlock(
    blocks: Block[],
    id: string,
    parentId: string | null = null,
): BlockPlace | null {
    const index = blocks.findIndex((block) => block.id === id);

    if (index !== -1) {
        return { parentId, index, siblings: blocks };
    }

    for (const block of blocks) {
        const place = locateBlock(block.children ?? [], id, block.id);

        if (place) {
            return place;
        }
    }

    return null;
}

// The type of a parent, or "root" for the page.
export function parentType(blocks: Block[], parentId: string | null): string {
    return parentId === null
        ? ROOT
        : (findBlock(blocks, parentId)?.type ?? ROOT);
}

// True when "id" is "ancestorId" or sits somewhere inside it.
export function isInside(
    blocks: Block[],
    ancestorId: string,
    id: string | null,
): boolean {
    if (id === null) {
        return false;
    }

    if (id === ancestorId) {
        return true;
    }

    const ancestor = findBlock(blocks, ancestorId);

    return ancestor !== null && findBlock(ancestor.children ?? [], id) !== null;
}

// Changes the children list of one parent (or the page when parentId is null).
export function updateChildren(
    blocks: Block[],
    parentId: string | null,
    update: (children: Block[]) => Block[],
): Block[] {
    if (parentId === null) {
        return update(blocks);
    }

    const parent = findBlock(blocks, parentId);

    return parent
        ? replaceBlock(blocks, parentId, {
              ...parent,
              children: update(parent.children ?? []),
          })
        : blocks;
}

export function removeBlock(blocks: Block[], id: string): Block[] {
    const place = locateBlock(blocks, id);

    return place
        ? updateChildren(blocks, place.parentId, (children) =>
              children.filter((block) => block.id !== id),
          )
        : blocks;
}

// Adds blocks at a position of a parent. The index is clamped to the list.
export function insertBlocks(
    blocks: Block[],
    parentId: string | null,
    index: number,
    items: Block[],
): Block[] {
    return updateChildren(blocks, parentId, (children) => {
        const next = [...children];
        next.splice(Math.max(0, Math.min(index, next.length)), 0, ...items);

        return next;
    });
}

// Moves a block to a position of a parent. The index is counted before the block is taken out.
// A block can not move into itself or its own children.
export function moveBlock(
    blocks: Block[],
    id: string,
    parentId: string | null,
    index: number,
): Block[] {
    const place = locateBlock(blocks, id);
    const block = findBlock(blocks, id);

    if (
        !place ||
        !block ||
        (parentId !== null && isInside(blocks, id, parentId))
    ) {
        return blocks;
    }

    const sameParent = place.parentId === parentId;

    if (sameParent && (index === place.index || index === place.index + 1)) {
        return blocks;
    }

    const target = sameParent && place.index < index ? index - 1 : index;

    return insertBlocks(removeBlock(blocks, id), parentId, target, [block]);
}

export type MoveDirection = 'up' | 'down' | 'into' | 'out';

// The place a keyboard move would take a block to, or null when there is none.
export function keyboardTarget(
    blocks: Block[],
    id: string,
    direction: MoveDirection,
    canDrop: (parent: string, child: string) => boolean,
): { parentId: string | null; index: number } | null {
    const place = locateBlock(blocks, id);
    const block = findBlock(blocks, id);

    if (!place || !block) {
        return null;
    }

    if (direction === 'up') {
        return place.index > 0
            ? { parentId: place.parentId, index: place.index - 1 }
            : null;
    }

    if (direction === 'down') {
        return place.index < place.siblings.length - 1
            ? { parentId: place.parentId, index: place.index + 2 }
            : null;
    }

    if (direction === 'into') {
        let target = place.siblings[place.index - 1];

        // Columns hold blocks in their last column.
        if (target?.type === 'columns' && target.children?.length) {
            target = target.children[target.children.length - 1];
        }

        return target && canDrop(target.type, block.type)
            ? { parentId: target.id, index: target.children?.length ?? 0 }
            : null;
    }

    // "out": after the parent; a column counts with its columns block.
    let outer =
        place.parentId === null ? null : locateBlock(blocks, place.parentId);

    if (outer && findBlock(blocks, place.parentId!)?.type === 'column') {
        outer = locateBlock(blocks, outer.parentId ?? '') ?? outer;
    }

    if (!outer) {
        return null;
    }

    return canDrop(parentType(blocks, outer.parentId), block.type)
        ? { parentId: outer.parentId, index: outer.index + 1 }
        : null;
}
