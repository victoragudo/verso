// A small keyed list that core, plugins and themes fill with register().
// Items are sorted by "order" (lower first), then by the order they were added.
export type Registry<T> = {
    register: (item: T) => () => void;
    unregister: (key: string) => void;
    get: (key: string) => T | undefined;
    has: (key: string) => boolean;
    all: () => T[];
    clear: () => void;
    subscribe: (listener: () => void) => () => void;
};

type Entry<T> = { item: T; position: number };

export function createRegistry<T>(
    keyOf: (item: T) => string,
    orderOf: (item: T) => number = () => 0,
): Registry<T> {
    const entries = new Map<string, Entry<T>>();
    const listeners = new Set<() => void>();
    let position = 0;
    let sorted: T[] | null = null;

    // Tells listeners (like React hooks) that the list changed.
    const changed = () => {
        sorted = null;
        listeners.forEach((listener) => listener());
    };

    const unregister = (key: string) => {
        if (entries.delete(key)) {
            changed();
        }
    };

    return {
        register(item) {
            const key = keyOf(item);
            const existing = entries.get(key);
            entries.set(key, {
                item,
                position: existing?.position ?? position++,
            });
            changed();

            return () => {
                if (entries.get(key)?.item === item) {
                    unregister(key);
                }
            };
        },
        unregister,
        get: (key) => entries.get(key)?.item,
        has: (key) => entries.has(key),
        all() {
            sorted ??= [...entries.values()]
                .sort(
                    (a, b) =>
                        orderOf(a.item) - orderOf(b.item) ||
                        a.position - b.position,
                )
                .map((entry) => entry.item);

            return sorted;
        },
        clear() {
            entries.clear();
            changed();
        },
        subscribe(listener) {
            listeners.add(listener);

            return () => listeners.delete(listener);
        },
    };
}
