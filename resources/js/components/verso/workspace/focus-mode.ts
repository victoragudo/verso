import { useCallback, useSyncExternalStore } from 'react';
import { readStored, useWorkspaceKey, writeStored } from './workspace-storage';

// Focus mode hides the admin sidebar and top bar while people work in a builder. It is kept per user.
const FOCUS_NAME = 'focus';
const ON = 'on';

const listeners = new Set<() => void>();
const memory = new Map<string, boolean>();

function subscribe(listener: () => void) {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
}

function read(key: string): boolean {
    if (!memory.has(key)) {
        memory.set(key, readStored(key) === ON);
    }

    return memory.get(key) ?? false;
}

export function setFocusMode(key: string, on: boolean): void {
    memory.set(key, on);
    writeStored(key, on ? ON : null);
    listeners.forEach((listener) => listener());
}

export function useFocusMode(): [boolean, (on: boolean) => void] {
    const key = useWorkspaceKey(FOCUS_NAME);
    const on = useSyncExternalStore(
        subscribe,
        () => read(key),
        () => false,
    );
    const set = useCallback((next: boolean) => setFocusMode(key, next), [key]);

    return [on, set];
}
