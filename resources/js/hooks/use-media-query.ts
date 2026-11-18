import { useCallback, useSyncExternalStore } from 'react';

// True while the CSS media query matches. Returns false on the server.
export function useMediaQuery(query: string): boolean {
    const subscribe = useCallback(
        (onChange: () => void) => {
            if (typeof window === 'undefined') {
                return () => {};
            }

            const list = window.matchMedia(query);
            list.addEventListener('change', onChange);

            return () => list.removeEventListener('change', onChange);
        },
        [query],
    );

    return useSyncExternalStore(
        subscribe,
        () =>
            typeof window === 'undefined'
                ? false
                : window.matchMedia(query).matches,
        () => false,
    );
}
