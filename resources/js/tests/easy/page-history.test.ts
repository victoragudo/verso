import { describe, expect, it } from 'vitest';
import { moveSection, setText } from '@/lib/easy/page-blocks';
import {
    canRedo,
    canUndo,
    isDirty,
    pageEditorReducer,
    startPageEditor,
} from '@/lib/easy/page-history';
import { bakeryBlocks } from './page-fixtures';

describe('page editor history', () => {
    const reduce = pageEditorReducer(2);

    it('starts clean with nothing to undo', () => {
        const state = startPageEditor(bakeryBlocks());

        expect(canUndo(state)).toBe(false);
        expect(canRedo(state)).toBe(false);
        expect(isDirty(state)).toBe(false);
    });

    it('undoes and redoes every change one by one, even quick text changes', () => {
        const start = bakeryBlocks();
        const first = setText(start, 'b_title001', 'content', 'Sourdough');
        const second = setText(
            first,
            'b_title001',
            'content',
            'Sourdough daily',
        );
        let state = startPageEditor(start);
        state = reduce(state, { type: 'change', blocks: first, now: 1000 });
        state = reduce(state, { type: 'change', blocks: second, now: 1001 });

        expect(isDirty(state)).toBe(true);
        state = reduce(state, { type: 'undo' });
        expect(state.history.value).toBe(first);
        state = reduce(state, { type: 'undo' });
        expect(state.history.value).toBe(start);
        expect(isDirty(state)).toBe(false);
        expect(canRedo(state)).toBe(true);
        state = reduce(state, { type: 'redo' });
        expect(state.history.value).toBe(first);
    });

    it('keeps only the newest steps', () => {
        let state = startPageEditor(bakeryBlocks());
        const one = moveSection(state.history.value, 'b_hero0001', 'down');
        const two = moveSection(one, 'b_hero0001', 'down');
        const three = moveSection(two, 'b_serv0001', 'down');

        for (const blocks of [one, two, three]) {
            state = reduce(state, { type: 'change', blocks });
        }

        expect(state.history.past).toEqual([one, two]);
    });

    it('ignores a change to the same list and drops redo after a new change', () => {
        const start = bakeryBlocks();
        const moved = moveSection(start, 'b_serv0001', 'up');
        let state = startPageEditor(start);

        expect(reduce(state, { type: 'change', blocks: start })).toBe(state);

        state = reduce(state, { type: 'change', blocks: moved });
        state = reduce(state, { type: 'undo' });
        state = reduce(state, {
            type: 'change',
            blocks: moveSection(start, 'b_gall0001', 'up'),
        });

        expect(canRedo(state)).toBe(false);
    });

    it('is clean again after saving', () => {
        const moved = moveSection(bakeryBlocks(), 'b_serv0001', 'up');
        let state = reduce(startPageEditor(bakeryBlocks()), {
            type: 'change',
            blocks: moved,
        });
        state = reduce(state, { type: 'saved', blocks: moved });

        expect(isDirty(state)).toBe(false);
        expect(canUndo(state)).toBe(true);
    });
});
