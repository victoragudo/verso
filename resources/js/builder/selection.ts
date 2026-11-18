import type { Block } from '@/types';
import { insertBlocks, locateBlock, updateChildren } from './tree';

// Blocks picked together with Shift+click, Cmd/Ctrl+click or Shift+Arrow.
// All picked blocks share one parent, so they can move and be grouped as one piece.
export type BlockSelection = {
    // The block the range starts from.
    anchor: string | null;
    // The block the range ends on. Shift+Arrow moves it.
    head: string | null;
    // Picked block ids, in page order.
    ids: string[];
};

export const EMPTY_SELECTION: BlockSelection = {
    anchor: null,
    head: null,
    ids: [],
};

export function selectOne(id: string): BlockSelection {
    return { anchor: id, head: id, ids: [id] };
}

function siblingsOf(blocks: Block[], ids: string[]) {
    const first = ids[0] ? locateBlock(blocks, ids[0]) : null;

    return first?.siblings ?? null;
}

function sameParent(blocks: Block[], a: string, b: string): boolean {
    const one = locateBlock(blocks, a);
    const two = locateBlock(blocks, b);

    return one !== null && two !== null && one.parentId === two.parentId;
}

function inPageOrder(blocks: Block[], ids: string[]): string[] {
    const siblings = siblingsOf(blocks, ids) ?? [];

    return siblings.map((block) => block.id).filter((id) => ids.includes(id));
}

// The blocks from "from" to "to" in their list, both included.
function range(blocks: Block[], from: string, to: string): string[] {
    const start = locateBlock(blocks, from);
    const end = locateBlock(blocks, to);

    if (!start || !end) {
        return [];
    }

    const [low, high] = [
        Math.min(start.index, end.index),
        Math.max(start.index, end.index),
    ];

    return start.siblings.slice(low, high + 1).map((block) => block.id);
}

// Shift+click: every block between the anchor and the clicked block.
export function selectRange(
    blocks: Block[],
    selection: BlockSelection,
    id: string,
): BlockSelection {
    const anchor = selection.anchor;

    if (anchor === null || !sameParent(blocks, anchor, id)) {
        return selectOne(id);
    }

    return { anchor, head: id, ids: range(blocks, anchor, id) };
}

// Cmd/Ctrl+click: adds or removes one block. A block of another list starts a new selection.
export function toggleInSelection(
    blocks: Block[],
    selection: BlockSelection,
    id: string,
): BlockSelection {
    const other = selection.ids[0];

    if (other === undefined || !sameParent(blocks, other, id)) {
        return selectOne(id);
    }

    const ids = selection.ids.includes(id)
        ? selection.ids.filter((item) => item !== id)
        : inPageOrder(blocks, [...selection.ids, id]);

    return ids.length === 0 ? EMPTY_SELECTION : { anchor: id, head: id, ids };
}

// Shift+ArrowUp / Shift+ArrowDown: moves the end of the range by one block.
export function extendSelection(
    blocks: Block[],
    selection: BlockSelection,
    from: string,
    direction: 'up' | 'down',
): BlockSelection {
    const anchor = selection.ids.includes(from)
        ? (selection.anchor ?? from)
        : from;
    const head = selection.ids.includes(from) ? (selection.head ?? from) : from;
    const place = locateBlock(blocks, head);

    if (!place) {
        return selection;
    }

    const next =
        place.siblings[place.index + (direction === 'up' ? -1 : 1)] ?? null;
    const newHead = next?.id ?? head;

    return { anchor, head: newHead, ids: range(blocks, anchor, newHead) };
}

// Keeps only ids that are still in the page, for example after an undo.
export function cleanSelection(
    blocks: Block[],
    selection: BlockSelection,
): BlockSelection {
    const ids = selection.ids.filter((id) => locateBlock(blocks, id) !== null);

    if (ids.length === selection.ids.length) {
        return selection;
    }

    return ids.length === 0
        ? EMPTY_SELECTION
        : { anchor: ids[0], head: ids[ids.length - 1], ids };
}

export function selectedBlocks(blocks: Block[], ids: string[]): Block[] {
    const siblings = siblingsOf(blocks, ids) ?? [];

    return siblings.filter((block) => ids.includes(block.id));
}

// Takes the picked blocks out of their list. Returns the list without them and where the first one was.
function takeOut(
    blocks: Block[],
    ids: string[],
): { blocks: Block[]; parentId: string | null; index: number } | null {
    const first = ids[0] ? locateBlock(blocks, ids[0]) : null;

    if (!first) {
        return null;
    }

    const index = first.siblings.findIndex((block) => ids.includes(block.id));
    const kept = first.siblings.filter((block) => !ids.includes(block.id));
    const parentId = first.parentId;

    return {
        blocks: updateChildren(blocks, parentId, () => kept),
        parentId,
        index,
    };
}

export function removeSelection(blocks: Block[], ids: string[]): Block[] {
    return takeOut(blocks, ids)?.blocks ?? blocks;
}

// Moves the picked blocks one place up or down, together. Blocks between them close up.
export function moveSelection(
    blocks: Block[],
    ids: string[],
    direction: 'up' | 'down',
): Block[] {
    const picked = selectedBlocks(blocks, ids);
    const out = takeOut(blocks, ids);
    const first = ids[0] ? locateBlock(blocks, ids[0]) : null;

    if (!out || !first || picked.length === 0) {
        return blocks;
    }

    const remaining = first.siblings.length - picked.length;
    const index = out.index + (direction === 'up' ? -1 : 1);

    if (index < 0 || index > remaining) {
        return blocks;
    }

    return insertBlocks(out.blocks, out.parentId, index, picked);
}

// Adds copies of the picked blocks after the last one. Returns the new ids so they become the selection.
export function duplicateSelection(
    blocks: Block[],
    ids: string[],
    clone: (block: Block) => Block,
): { blocks: Block[]; ids: string[] } {
    const picked = selectedBlocks(blocks, ids);
    const last = locateBlock(blocks, picked[picked.length - 1]?.id ?? '');

    if (!last) {
        return { blocks, ids };
    }

    const copies = picked.map(clone);

    return {
        blocks: insertBlocks(blocks, last.parentId, last.index + 1, copies),
        ids: copies.map((block) => block.id),
    };
}

// Puts the picked blocks, in page order, inside a new group block at the place of the first one.
export function groupSelection(
    blocks: Block[],
    ids: string[],
    group: Block,
): Block[] {
    const picked = selectedBlocks(blocks, ids);
    const out = takeOut(blocks, ids);

    if (!out || picked.length === 0) {
        return blocks;
    }

    return insertBlocks(out.blocks, out.parentId, out.index, [
        { ...group, children: picked },
    ]);
}

// How a click picks blocks: one block, a range (Shift) or one more or less (Cmd on Mac, Ctrl elsewhere).
export type PickMode = 'one' | 'range' | 'toggle';

export function pickMode(event: {
    shiftKey: boolean;
    metaKey: boolean;
    ctrlKey: boolean;
}): PickMode {
    if (event.shiftKey) {
        return 'range';
    }

    return event.metaKey || event.ctrlKey ? 'toggle' : 'one';
}

export function applyPick(
    blocks: Block[],
    selection: BlockSelection,
    id: string,
    mode: PickMode,
): BlockSelection {
    if (mode === 'range') {
        return selectRange(blocks, selection, id);
    }

    return mode === 'toggle'
        ? toggleInSelection(blocks, selection, id)
        : selectOne(id);
}
