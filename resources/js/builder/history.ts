import type { Block } from '@/types';

// One undo history for the whole document. Each entry is the full block list, so any change can be undone.
export type History = {
    value: Block[];
    past: Block[][];
    future: Block[][];
    // When the last change happened and which text block it touched, to group typing into one step.
    time: number;
    key: string | null;
};

export type HistoryOptions = { limit: number; mergeMs: number };

export function startHistory(value: Block[]): History {
    return { value, past: [], future: [], time: 0, key: null };
}

// The id of the only block whose text changed, or null for any other kind of change.
export function typingKey(previous: Block[], next: Block[]): string | null {
    if (previous.length !== next.length) {
        return null;
    }

    let key: string | null = null;

    for (let index = 0; index < next.length; index++) {
        const before = previous[index];
        const after = next[index];

        if (before === after) {
            continue;
        }

        if (
            before.id !== after.id ||
            before.type !== after.type ||
            key !== null
        ) {
            return null;
        }

        const sameAttrs =
            before.attrs === after.attrs ||
            JSON.stringify(before.attrs ?? {}) ===
                JSON.stringify(after.attrs ?? {});

        if (!sameAttrs) {
            return null;
        }

        if (before.content !== after.content) {
            key = after.id;
            continue;
        }

        const inner = typingKey(before.children ?? [], after.children ?? []);

        if (inner === null) {
            return null;
        }

        key = inner;
    }

    return key;
}

// Adds a change. Quick typing in the same block becomes one step.
export function recordChange(
    history: History,
    next: Block[],
    now: number,
    options: HistoryOptions,
): History {
    const key = typingKey(history.value, next);
    const merge =
        key !== null &&
        key === history.key &&
        now - history.time < options.mergeMs &&
        history.past.length > 0;

    return {
        value: next,
        past: merge
            ? history.past
            : [...history.past, history.value].slice(-options.limit),
        future: [],
        time: now,
        key,
    };
}

export function undo(history: History): History {
    const previous = history.past[history.past.length - 1];

    return previous
        ? {
              value: previous,
              past: history.past.slice(0, -1),
              future: [history.value, ...history.future],
              time: 0,
              key: null,
          }
        : history;
}

export function redo(history: History): History {
    const [next, ...rest] = history.future;

    return next
        ? {
              value: next,
              past: [...history.past, history.value],
              future: rest,
              time: 0,
              key: null,
          }
        : history;
}
