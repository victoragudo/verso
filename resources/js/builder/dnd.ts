import type { Block } from '@/types';

// Data type set on drags that carry blocks.
export const DRAG_MIME = 'application/x-verso-block';

// What is being dragged: a block already in the page, or new blocks from the Add panel.
export type DragPayload =
    | { kind: 'move'; id: string; type: string }
    | { kind: 'insert'; key: string; blocks: Block[] };

// Where the drop marker is: before or after a block of one list, or inside an empty list.
export type DropSpot = {
    parentId: string | null;
    index: number;
    // Shown on this block row: "before" or "after" it. Null for an empty list.
    rowId: string | null;
    edge: 'before' | 'after' | 'inside';
};

type State = { payload: DragPayload | null; spot: DropSpot | null };

let state: State = { payload: null, spot: null };
const listeners = new Set<() => void>();

function set(next: Partial<State>) {
    state = { ...state, ...next };
    listeners.forEach((listener) => listener());
}

// One shared drag state, so every list, the outline and the Add panel agree on the drop marker.
export const dragState = {
    get: (): State => state,
    start: (payload: DragPayload) => set({ payload, spot: null }),
    point: (spot: DropSpot | null) => {
        const current = state.spot;

        if (
            current?.parentId !== spot?.parentId ||
            current?.index !== spot?.index ||
            current?.rowId !== spot?.rowId ||
            current?.edge !== spot?.edge
        ) {
            set({ spot });
        }
    },
    end: () => set({ payload: null, spot: null }),
    subscribe: (listener: () => void) => {
        listeners.add(listener);

        return () => listeners.delete(listener);
    },
};

// Types of the top blocks a drag would add, used to check the drop rules.
export function payloadTypes(payload: DragPayload): string[] {
    return payload.kind === 'move'
        ? [payload.type]
        : payload.blocks.map((block) => block.type);
}

// "before" or "after", from where the pointer is on a row. Rows that hold blocks also have an "inside" middle part.
export function edgeFromPointer(
    clientY: number,
    rect: { top: number; height: number },
    container: boolean,
): 'before' | 'after' | 'inside' {
    const offset = (clientY - rect.top) / Math.max(rect.height, 1);

    if (container && offset > 0.25 && offset < 0.75) {
        return 'inside';
    }

    return offset < 0.5 ? 'before' : 'after';
}
