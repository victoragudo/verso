import { recordChange, redo, startHistory, undo } from '@/builder/history';
import type { History } from '@/builder/history';
import type { Block } from '@/types';

// State of the visual page editor: the undo history of the blocks and the blocks the server has.
export type PageEditorState = {
    history: History;
    saved: Block[];
};

export type PageEditorAction =
    | { type: 'change'; blocks: Block[]; now?: number }
    | { type: 'undo' }
    | { type: 'redo' }
    | { type: 'saved'; blocks: Block[] };

export function startPageEditor(blocks: Block[]): PageEditorState {
    return { history: startHistory(blocks), saved: blocks };
}

// Every change is one undo step (no merging), so "Undo" always reverts exactly one action.
export function pageEditorReducer(limit: number) {
    return (
        state: PageEditorState,
        action: PageEditorAction,
    ): PageEditorState => {
        switch (action.type) {
            case 'change':
                return action.blocks === state.history.value
                    ? state
                    : {
                          ...state,
                          history: recordChange(
                              state.history,
                              action.blocks,
                              action.now ?? Date.now(),
                              { limit: Math.max(1, limit), mergeMs: 0 },
                          ),
                      };
            case 'undo':
                return { ...state, history: undo(state.history) };
            case 'redo':
                return { ...state, history: redo(state.history) };
            case 'saved':
                return { ...state, saved: action.blocks };
        }
    };
}

export function canUndo(state: PageEditorState): boolean {
    return state.history.past.length > 0;
}

export function canRedo(state: PageEditorState): boolean {
    return state.history.future.length > 0;
}

// True when the blocks differ from the last saved ones.
export function isDirty(state: PageEditorState): boolean {
    return (
        state.history.value !== state.saved &&
        JSON.stringify(state.history.value) !== JSON.stringify(state.saved)
    );
}
