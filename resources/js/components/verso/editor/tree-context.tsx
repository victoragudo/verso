import { createContext, useContext, useRef, useState } from 'react';
import { builder, canDrop } from '@/builder';
import type { DragPayload, DropSpot } from '@/builder/dnd';
import { payloadTypes } from '@/builder/dnd';
import {
    findBlock,
    insertBlocks,
    isInside,
    keyboardTarget,
    locateBlock,
    moveBlock,
    parentType,
} from '@/builder/tree';
import type { MoveDirection } from '@/builder/tree';
import { msg, t } from '@/lib/i18n';
import type { Block } from '@/types';

// Plain words for each keyboard move, used in menus and announcements.
export const MOVE_LABELS: Record<MoveDirection, string> = {
    up: msg('Move up'),
    down: msg('Move down'),
    into: msg('Move into the block above'),
    out: msg('Move out of this group'),
};

export type BuilderTree = {
    blocks: Block[];
    accepts: (payload: DragPayload, parentId: string | null) => boolean;
    drop: (payload: DragPayload, spot: DropSpot) => void;
    canMove: (id: string, direction: MoveDirection) => boolean;
    move: (id: string, direction: MoveDirection) => void;
    announce: (text: string) => void;
    message: string;
    // Copy a block to the system clipboard, and paste copied blocks after a block.
    copyBlock?: (id: string) => void;
    pasteAfter?: (id: string) => void;
};

export const TreeContext = createContext<BuilderTree | null>(null);

export function useTree(): BuilderTree | null {
    return useContext(TreeContext);
}

export function blockLabel(type: string): string {
    const label = builder.blocks.get(type)?.label;

    return label ? t(label) : type;
}

// Puts focus back on the drag handle of a block after it moved, so keyboard people keep their place.
function focusHandle(id: string) {
    requestAnimationFrame(() => {
        const handle = document.querySelector<HTMLElement>(
            `[data-block-id="${CSS.escape(id)}"] [data-drag-handle]`,
        );
        handle?.closest('[data-block-id]')?.scrollIntoView({
            block: 'nearest',
            inline: 'nearest',
        });
        handle?.focus({ preventScroll: true });
    });
}

// Tree actions shared by the canvas, the outline and the Add panel. All changes go through the root list.
export function useBuilderTree(
    blocks: Block[],
    onChange: (blocks: Block[]) => void,
): BuilderTree {
    const [message, setMessage] = useState('');
    const timer = useRef<number | undefined>(undefined);

    // Clears first and waits a little, so screen readers read the new text after the focus moves.
    const announce = (text: string) => {
        setMessage('');
        window.clearTimeout(timer.current);
        timer.current = window.setTimeout(() => setMessage(text), 150);
    };

    const moved = (next: Block[], id: string, type: string) => {
        const block = blockLabel(type);
        const place = locateBlock(next, id);

        if (!place) {
            return t('{block} moved.', { block });
        }

        const position = {
            block,
            position: place.index + 1,
            total: place.siblings.length,
        };

        return place.parentId === null
            ? t(
                  '{block} moved to position {position} of {total} in the page.',
                  position,
              )
            : t(
                  '{block} moved to position {position} of {total} in {parent}.',
                  {
                      ...position,
                      parent: blockLabel(parentType(next, place.parentId)),
                  },
              );
    };

    const added = (next: Block[], id: string, type: string) => {
        const block = blockLabel(type);
        const place = locateBlock(next, id);

        if (!place) {
            return t('{block} added.', { block });
        }

        const position = {
            block,
            position: place.index + 1,
            total: place.siblings.length,
        };

        return place.parentId === null
            ? t(
                  '{block} added at position {position} of {total} in the page.',
                  position,
              )
            : t(
                  '{block} added at position {position} of {total} in {parent}.',
                  {
                      ...position,
                      parent: blockLabel(parentType(next, place.parentId)),
                  },
              );
    };

    return {
        blocks,
        message,
        announce,
        accepts(payload, parentId) {
            const type = parentType(blocks, parentId);

            if (
                payload.kind === 'move' &&
                isInside(blocks, payload.id, parentId)
            ) {
                return false;
            }

            return payloadTypes(payload).every((child) => canDrop(type, child));
        },
        drop(payload, spot) {
            if (payload.kind === 'move') {
                const next = moveBlock(
                    blocks,
                    payload.id,
                    spot.parentId,
                    spot.index,
                );

                if (next !== blocks) {
                    onChange(next);
                    announce(moved(next, payload.id, payload.type));
                }

                return;
            }

            const next = insertBlocks(
                blocks,
                spot.parentId,
                spot.index,
                payload.blocks,
            );
            onChange(next);
            const first = payload.blocks[0];

            if (first) {
                announce(added(next, first.id, first.type));
            }
        },
        canMove: (id, direction) =>
            keyboardTarget(blocks, id, direction, canDrop) !== null,
        move(id, direction) {
            const target = keyboardTarget(blocks, id, direction, canDrop);
            const block = findBlock(blocks, id);

            if (!target || !block) {
                return;
            }

            const next = moveBlock(blocks, id, target.parentId, target.index);
            onChange(next);
            announce(moved(next, id, block.type));
            focusHandle(id);
        },
    };
}
