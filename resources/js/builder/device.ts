import { useSyncExternalStore } from 'react';

// Screen size picked in the Computer / Tablet / Phone switch. The Styles panel edits it and the canvas previews it.
let current = 'base';
const listeners = new Set<() => void>();

export const editorDevice = {
    get: (): string => current,
    set: (device: string) => {
        if (device !== current) {
            current = device;
            listeners.forEach((listener) => listener());
        }
    },
    subscribe: (listener: () => void) => {
        listeners.add(listener);

        return () => {
            listeners.delete(listener);
        };
    },
};

export function useEditorDevice(): [string, (device: string) => void] {
    const device = useSyncExternalStore(
        editorDevice.subscribe,
        editorDevice.get,
        editorDevice.get,
    );

    return [device, editorDevice.set];
}
