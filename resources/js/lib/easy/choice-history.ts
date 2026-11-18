// A short list of earlier picks on "Make it yours", so "Undo" goes back one pick at a time.

// mergeKey: picks with the same key in a row (like typing one color code) make one step, until settleChoice().
export type ChoiceHistory<T> = {
    past: T[];
    current: T;
    mergeKey?: string | null;
};

const same = (first: unknown, second: unknown) =>
    JSON.stringify(first) === JSON.stringify(second);

export function startChoices<T>(current: T): ChoiceHistory<T> {
    return { past: [], current, mergeKey: null };
}

// Records a new pick. The same pick again adds no step. Only the last `limit` steps are kept.
// With a mergeKey equal to the one of the last pick, the last step is updated instead of adding one.
export function pickChoice<T>(
    history: ChoiceHistory<T>,
    next: T,
    limit: number,
    mergeKey: string | null = null,
): ChoiceHistory<T> {
    if (same(next, history.current)) {
        return history;
    }

    if (
        mergeKey !== null &&
        history.mergeKey === mergeKey &&
        history.past.length > 0
    ) {
        return same(next, history.past.at(-1))
            ? { past: history.past.slice(0, -1), current: next, mergeKey: null }
            : { past: history.past, current: next, mergeKey };
    }

    return {
        past: [...history.past, history.current].slice(-Math.max(1, limit)),
        current: next,
        mergeKey,
    };
}

// Ends a run of merged picks, so the next pick is a step of its own.
export function settleChoice<T>(history: ChoiceHistory<T>): ChoiceHistory<T> {
    return history.mergeKey ? { ...history, mergeKey: null } : history;
}

// Goes back to the pick before the last one.
export function undoChoice<T>(history: ChoiceHistory<T>): ChoiceHistory<T> {
    const previous = history.past.at(-1);

    return previous === undefined
        ? history
        : {
              past: history.past.slice(0, -1),
              current: previous,
              mergeKey: null,
          };
}

export function canUndoChoice<T>(history: ChoiceHistory<T>): boolean {
    return history.past.length > 0;
}
