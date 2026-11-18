import { describe, expect, it } from 'vitest';
import {
    canUndoChoice,
    pickChoice,
    settleChoice,
    startChoices,
    undoChoice,
} from '@/lib/easy/choice-history';

describe('choice history', () => {
    it('goes back one pick at a time', () => {
        let history = startChoices({ accent: '#2e5e4e' });
        history = pickChoice(history, { accent: '#c2410c' }, 10);
        history = pickChoice(history, { accent: '#1d4ed8' }, 10);

        expect(canUndoChoice(history)).toBe(true);
        history = undoChoice(history);
        expect(history.current).toEqual({ accent: '#c2410c' });
        history = undoChoice(history);
        expect(history.current).toEqual({ accent: '#2e5e4e' });
        expect(canUndoChoice(history)).toBe(false);
        expect(undoChoice(history)).toBe(history);
    });

    it('adds no step for the same pick and keeps only the last steps', () => {
        let history = startChoices({ preset: 'calm' });
        expect(pickChoice(history, { preset: 'calm' }, 2)).toBe(history);

        for (const preset of ['bold', 'playful', 'elegant']) {
            history = pickChoice(history, { preset }, 2);
        }

        expect(history.past).toEqual([
            { preset: 'bold' },
            { preset: 'playful' },
        ]);
    });

    it('makes one step of picks with the same merge key, like typing a color code', () => {
        let history = startChoices({ accent: '#2e5e4e' });

        for (const accent of ['#c2410c', '#c2410d', '#c2410e']) {
            history = pickChoice(history, { accent }, 10, 'color:accent');
        }

        expect(history.past).toEqual([{ accent: '#2e5e4e' }]);
        expect(history.current).toEqual({ accent: '#c2410e' });
        expect(undoChoice(history).current).toEqual({ accent: '#2e5e4e' });
    });

    it('starts a new step after settling, for another key or without a key', () => {
        let history = startChoices({ accent: '#2e5e4e', text: '#1b2420' });
        history = pickChoice(
            history,
            { accent: '#c2410c', text: '#1b2420' },
            10,
            'color:accent',
        );
        history = settleChoice(history);
        history = pickChoice(
            history,
            { accent: '#1d4ed8', text: '#1b2420' },
            10,
            'color:accent',
        );
        history = pickChoice(
            history,
            { accent: '#1d4ed8', text: '#111111' },
            10,
            'color:text',
        );
        history = pickChoice(
            history,
            { accent: '#9a3412', text: '#111111' },
            10,
        );

        expect(history.past).toHaveLength(4);
        expect(settleChoice(startChoices({ accent: '#2e5e4e' }))).toEqual(
            startChoices({ accent: '#2e5e4e' }),
        );
    });

    it('drops the step when merged typing ends on the color it started from', () => {
        let history = startChoices({ accent: '#2e5e4e' });
        history = pickChoice(
            history,
            { accent: '#2e5e4f' },
            10,
            'color:accent',
        );
        history = pickChoice(
            history,
            { accent: '#2e5e4e' },
            10,
            'color:accent',
        );

        expect(canUndoChoice(history)).toBe(false);
        expect(history.current).toEqual({ accent: '#2e5e4e' });
    });
});
