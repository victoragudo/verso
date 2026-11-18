import { GripVertical, MoreHorizontal } from 'lucide-react';
import type { DragEvent } from 'react';
import { useSyncExternalStore } from 'react';
import { canDrop } from '@/builder';
import {
    DRAG_MIME,
    dragState,
    edgeFromPointer,
    payloadTypes,
} from '@/builder/dnd';
import type { DropSpot } from '@/builder/dnd';
import { pickMode } from '@/builder/selection';
import type { PickMode } from '@/builder/selection';
import type { MoveDirection } from '@/builder/tree';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { Block } from '@/types';
import { plainText } from './block-types';
import { blockLabel, MOVE_LABELS, useTree } from './tree-context';

const DIRECTIONS: MoveDirection[] = ['up', 'down', 'into', 'out'];

// Most characters of block text shown next to its name.
const PREVIEW_LENGTH = 40;

// A short text that tells blocks of the same type apart, like the start of a heading.
export function outlinePreview(block: Block): string {
    const text = plainText(block.content).trim();

    return text.length > PREVIEW_LENGTH
        ? `${text.slice(0, PREVIEW_LENGTH)}…`
        : text;
}

// The block that receives children dropped "inside" a row: the last column for columns.
function innerTarget(block: Block): Block {
    return block.type === 'columns' && block.children?.length
        ? block.children[block.children.length - 1]
        : block;
}

type Props = {
    selectedId: string | null;
    onSelect: (id: string) => void;
    // Blocks picked together in the canvas. Shift or Cmd/Ctrl+click here picks blocks too.
    selectedIds?: string[];
    onPick?: (id: string, mode: PickMode) => void;
};

// The outline: a tree of every block, to select, drag or move blocks, also deep inside sections.
export function OutlineView({
    selectedId,
    onSelect,
    selectedIds = [],
    onPick,
}: Props) {
    const isSelected = (id: string) =>
        selectedIds.length > 1 ? selectedIds.includes(id) : selectedId === id;
    const tree = useTree();
    const drag = useSyncExternalStore(
        dragState.subscribe,
        dragState.get,
        dragState.get,
    );

    if (!tree) {
        return null;
    }

    if (tree.blocks.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                {t('The page is empty. Add a block to start.')}
            </p>
        );
    }

    const over = (
        event: DragEvent,
        block: Block,
        parentId: string | null,
        index: number,
    ) => {
        const payload = dragState.get().payload;

        if (!payload) {
            return;
        }

        const target = innerTarget(block);
        const fitsInside = payloadTypes(payload).every((type) =>
            canDrop(target.type, type),
        );
        const edge = edgeFromPointer(
            event.clientY,
            event.currentTarget.getBoundingClientRect(),
            fitsInside,
        );
        const spot: DropSpot =
            edge === 'inside'
                ? {
                      parentId: target.id,
                      index: target.children?.length ?? 0,
                      rowId: block.id,
                      edge,
                  }
                : {
                      parentId,
                      index: index + (edge === 'after' ? 1 : 0),
                      rowId: block.id,
                      edge,
                  };

        if (!tree.accepts(payload, spot.parentId)) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        dragState.point(spot);
    };

    const drop = (event: DragEvent) => {
        const { payload, spot } = dragState.get();
        event.preventDefault();
        event.stopPropagation();

        if (payload && spot) {
            tree.drop(payload, spot);
        }

        dragState.end();
    };

    const renderList = (
        blocks: Block[],
        parentId: string | null,
        depth: number,
    ) => (
        <ul className={cn('grid gap-0.5', depth > 0 && 'ms-3 border-s ps-2')}>
            {blocks.map((block, index) => {
                const label = blockLabel(block.type);
                const preview = outlinePreview(block);
                const spot =
                    drag.spot?.rowId === block.id ? drag.spot.edge : null;

                return (
                    <li key={block.id} data-block-id={block.id}>
                        <div
                            onDragOver={(event) =>
                                over(event, block, parentId, index)
                            }
                            onDrop={drop}
                            className={cn(
                                'group flex items-center gap-0.5 rounded-md pe-1 transition-colors hover:bg-accent/60',
                                isSelected(block.id) && 'bg-accent',
                                spot === 'inside' && 'ring-2 ring-primary',
                                spot === 'before' &&
                                    'shadow-[0_-2px_0_0_var(--color-blue-600)]',
                                spot === 'after' &&
                                    'shadow-[0_2px_0_0_var(--color-blue-600)]',
                                drag.payload?.kind === 'move' &&
                                    drag.payload.id === block.id &&
                                    'opacity-40',
                            )}
                        >
                            <button
                                type="button"
                                draggable
                                data-drag-handle
                                aria-label={t('Drag to move {block} block', {
                                    block: label,
                                })}
                                onDragStart={(event) => {
                                    event.dataTransfer.effectAllowed = 'move';
                                    event.dataTransfer.setData(
                                        DRAG_MIME,
                                        block.id,
                                    );
                                    dragState.start({
                                        kind: 'move',
                                        id: block.id,
                                        type: block.type,
                                    });
                                }}
                                onDragEnd={() => dragState.end()}
                                className="flex size-7 shrink-0 cursor-grab touch-none items-center justify-center rounded text-muted-foreground select-none hover:bg-background active:cursor-grabbing"
                            >
                                <GripVertical className="size-3.5" />
                            </button>
                            <button
                                type="button"
                                aria-current={
                                    isSelected(block.id) ? 'true' : undefined
                                }
                                onClick={(event) => {
                                    const mode = pickMode(event);

                                    if (mode !== 'one' && onPick) {
                                        onPick(block.id, mode);

                                        return;
                                    }

                                    onPick?.(block.id, 'one');
                                    onSelect(block.id);
                                    document
                                        .querySelector(
                                            `[data-test="block-editor"] [data-block-id="${CSS.escape(block.id)}"]`,
                                        )
                                        ?.scrollIntoView({ block: 'nearest' });
                                }}
                                className="flex min-w-0 flex-1 items-baseline gap-1.5 py-1 text-start text-sm"
                            >
                                <span className="shrink-0 font-medium">
                                    {label}
                                </span>
                                {preview && (
                                    <span className="truncate text-xs text-muted-foreground">
                                        {preview}
                                    </span>
                                )}
                            </button>
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <button
                                        type="button"
                                        aria-label={t('{block} block moves', {
                                            block: label,
                                        })}
                                        className="flex size-7 shrink-0 items-center justify-center rounded text-muted-foreground hover:bg-background"
                                    >
                                        <MoreHorizontal className="size-3.5" />
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    {DIRECTIONS.map((direction) => (
                                        <DropdownMenuItem
                                            key={direction}
                                            disabled={
                                                !tree.canMove(
                                                    block.id,
                                                    direction,
                                                )
                                            }
                                            onSelect={() =>
                                                tree.move(block.id, direction)
                                            }
                                        >
                                            {t(MOVE_LABELS[direction])}
                                        </DropdownMenuItem>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                        {(block.children?.length ?? 0) > 0 &&
                            renderList(
                                block.children ?? [],
                                block.id,
                                depth + 1,
                            )}
                    </li>
                );
            })}
        </ul>
    );

    return (
        <nav aria-label={t('Page outline')} data-test="outline-view">
            {renderList(tree.blocks, null, 0)}
        </nav>
    );
}
