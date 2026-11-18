import { xsrfToken } from '@/lib/json-request';
import type { Block } from '@/types';
import type { LibraryItem } from './types';

// The design library of the site in the editor: saved synced blocks and patterns. Saving one adds it at once.
let items: LibraryItem[] = [];
let settings: { canSave: boolean; storeUrl: string; editUrl: string } | null =
    null;
const listeners = new Set<() => void>();

function publish(next: LibraryItem[]): void {
    items = next;
    listeners.forEach((listener) => listener());
}

export const library = {
    set(
        next: LibraryItem[],
        options: { canSave: boolean; storeUrl: string; editUrl: string } | null,
    ) {
        settings = options;

        if (JSON.stringify(next) !== JSON.stringify(items)) {
            publish(next);
        }
    },
    all: (): LibraryItem[] => items,
    find: (id: number): LibraryItem | undefined =>
        items.find((item) => item.id === id),
    available: (): boolean => settings !== null,
    canSave: (): boolean => settings?.canSave === true,
    // Address of the library page of one item.
    editUrl: (id: number): string =>
        settings ? settings.editUrl.replace(/\/0$/, `/${id}`) : '',
    subscribe(listener: () => void) {
        listeners.add(listener);

        return () => {
            listeners.delete(listener);
        };
    },
};

// Saves blocks as a synced block or a pattern. Gives the new item.
export async function saveToLibrary(input: {
    kind: LibraryItem['kind'];
    title: string;
    category: string;
    blocks: Block[];
}): Promise<LibraryItem> {
    if (!settings) {
        throw new Error('The library is not available here.');
    }

    const response = await fetch(settings.storeUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: JSON.stringify(input),
    });

    if (!response.ok) {
        const answer = (await response.json().catch(() => ({}))) as {
            message?: string;
        };

        throw new Error(answer.message ?? String(response.status));
    }

    const { item } = (await response.json()) as { item: LibraryItem };
    publish([...items, item]);

    return item;
}

// The "Save to the library" dialog of the editor: any block menu can open it with some blocks.
type DialogRequest = {
    blocks: Block[];
    onSaved: (item: LibraryItem) => void;
} | null;
let request: DialogRequest = null;
const dialogListeners = new Set<() => void>();

export const libraryDialog = {
    open(blocks: Block[], onSaved: (item: LibraryItem) => void) {
        request = { blocks, onSaved };
        dialogListeners.forEach((listener) => listener());
    },
    close() {
        request = null;
        dialogListeners.forEach((listener) => listener());
    },
    read: (): DialogRequest => request,
    subscribe(listener: () => void) {
        dialogListeners.add(listener);

        return () => {
            dialogListeners.delete(listener);
        };
    },
};

// The block that takes the place of blocks saved as a synced block.
export function syncedBlockFor(item: LibraryItem, id: string): Block {
    return {
        id,
        type: 'synced',
        attrs: { ref: item.id },
        content: '',
        children: [],
    };
}
