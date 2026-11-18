import { describe, expect, it } from 'vitest';
import {
    cleanSelection,
    duplicateSelection,
    applyPick,
    EMPTY_SELECTION,
    extendSelection,
    groupSelection,
    moveSelection,
    pickMode,
    removeSelection,
    selectOne,
    selectRange,
    toggleInSelection,
} from '@/builder/selection';
import type { Block } from '@/types';

function text(id: string, content: string): Block {
    return { id, type: 'paragraph', attrs: {}, content, children: [] };
}

// A bakery landing page: an intro, three opening hours lines and a section with two offers.
const page: Block[] = [
    text('b_intro', 'Fresh bread from our wood oven in Lavapiés.'),
    text('b_mon', 'Monday to Friday: 7:00 to 19:00'),
    text('b_sat', 'Saturday: 8:00 to 14:00'),
    text('b_sun', 'Sunday: closed'),
    {
        id: 'b_offers',
        type: 'section',
        attrs: {},
        children: [
            text('b_rye', 'Rye sourdough, 4.20 euros'),
            text('b_croissant', 'Butter croissant, 1.60 euros'),
        ],
    },
];

const ids = (blocks: Block[]) => blocks.map((block) => block.id);

describe('picking blocks', () => {
    it('selects a range with Shift+click from the anchor', () => {
        expect(selectRange(page, selectOne('b_mon'), 'b_sun').ids).toEqual([
            'b_mon',
            'b_sat',
            'b_sun',
        ]);
    });

    it('selects a range backwards and keeps page order', () => {
        const selection = selectRange(page, selectOne('b_sun'), 'b_intro');

        expect(selection.ids).toEqual(['b_intro', 'b_mon', 'b_sat', 'b_sun']);
        expect(selection.anchor).toBe('b_sun');
    });

    it('starts again when Shift+click lands in another list', () => {
        expect(selectRange(page, selectOne('b_mon'), 'b_rye')).toEqual(
            selectOne('b_rye'),
        );
    });

    it('toggles single blocks with Cmd or Ctrl+click in page order', () => {
        let selection = toggleInSelection(page, selectOne('b_sun'), 'b_intro');
        selection = toggleInSelection(page, selection, 'b_sat');

        expect(selection.ids).toEqual(['b_intro', 'b_sat', 'b_sun']);
        expect(toggleInSelection(page, selection, 'b_sat').ids).toEqual([
            'b_intro',
            'b_sun',
        ]);
    });

    it('clears the selection when the last block is toggled off', () => {
        expect(toggleInSelection(page, selectOne('b_mon'), 'b_mon')).toEqual(
            EMPTY_SELECTION,
        );
    });

    it('extends with Shift+Arrow and shrinks when it goes back', () => {
        let selection = extendSelection(
            page,
            selectOne('b_mon'),
            'b_mon',
            'down',
        );
        selection = extendSelection(page, selection, 'b_mon', 'down');

        expect(selection.ids).toEqual(['b_mon', 'b_sat', 'b_sun']);

        selection = extendSelection(page, selection, 'b_mon', 'up');

        expect(selection.ids).toEqual(['b_mon', 'b_sat']);
    });

    it('stops at the end of the list', () => {
        const selection = extendSelection(
            page,
            selectOne('b_croissant'),
            'b_croissant',
            'down',
        );

        expect(selection.ids).toEqual(['b_croissant']);
    });

    it('drops ids that are no longer in the page', () => {
        const selection = {
            anchor: 'b_mon',
            head: 'b_gone',
            ids: ['b_mon', 'b_gone'],
        };

        expect(cleanSelection(page, selection).ids).toEqual(['b_mon']);
    });
});

describe('changing picked blocks', () => {
    const hours = ['b_mon', 'b_sat', 'b_sun'];

    it('moves picked blocks up together', () => {
        expect(ids(moveSelection(page, hours, 'up'))).toEqual([
            'b_mon',
            'b_sat',
            'b_sun',
            'b_intro',
            'b_offers',
        ]);
    });

    it('moves picked blocks down together and closes gaps', () => {
        expect(ids(moveSelection(page, ['b_intro', 'b_sat'], 'down'))).toEqual([
            'b_mon',
            'b_intro',
            'b_sat',
            'b_sun',
            'b_offers',
        ]);
    });

    it('does not move past the edges', () => {
        expect(moveSelection(page, ['b_intro', 'b_mon'], 'up')).toBe(page);
        expect(moveSelection(page, ['b_rye', 'b_croissant'], 'down')).toBe(
            page,
        );
    });

    it('duplicates picked blocks after the last one', () => {
        let count = 0;
        const result = duplicateSelection(
            page,
            ['b_mon', 'b_sat'],
            (block) => ({
                ...block,
                id: `b_copy_${++count}`,
            }),
        );

        expect(ids(result.blocks)).toEqual([
            'b_intro',
            'b_mon',
            'b_sat',
            'b_copy_1',
            'b_copy_2',
            'b_sun',
            'b_offers',
        ]);
        expect(result.ids).toEqual(['b_copy_1', 'b_copy_2']);
    });

    it('removes picked blocks inside a section', () => {
        const next = removeSelection(page, ['b_rye']);

        expect(ids(next[4].children ?? [])).toEqual(['b_croissant']);
    });

    it('groups picked blocks into a section at the first place', () => {
        const next = groupSelection(page, ['b_intro', 'b_sat', 'b_sun'], {
            id: 'b_hours',
            type: 'section',
            attrs: { tag: 'section' },
            children: [],
        });

        expect(ids(next)).toEqual(['b_hours', 'b_mon', 'b_offers']);
        expect(ids(next[0].children ?? [])).toEqual([
            'b_intro',
            'b_sat',
            'b_sun',
        ]);
    });
});

describe('pickMode and applyPick', () => {
    it.each([
        [{ shiftKey: true, metaKey: false, ctrlKey: false }, 'range'],
        [{ shiftKey: false, metaKey: true, ctrlKey: false }, 'toggle'],
        [{ shiftKey: false, metaKey: false, ctrlKey: true }, 'toggle'],
        [{ shiftKey: false, metaKey: false, ctrlKey: false }, 'one'],
    ] as const)('reads %j as %s', (event, mode) => {
        expect(pickMode(event)).toBe(mode);
    });

    it('picks one block on a plain click', () => {
        expect(
            applyPick(
                page,
                selectRange(page, selectOne('b_mon'), 'b_sun'),
                'b_intro',
                'one',
            ),
        ).toEqual(selectOne('b_intro'));
    });
});
