import { useCallback, useRef } from 'react';

// Remembers which element had focus when an overlay opens, so focus can go
// back there when it closes. Radix only does this for its own trigger.
export function useReturnFocus() {
    const previous = useRef<HTMLElement | null>(null);

    const remember = useCallback(() => {
        const active = document.activeElement;
        previous.current =
            active instanceof HTMLElement && active !== document.body
                ? active
                : null;
    }, []);

    // Use as onCloseAutoFocus on a Radix dialog or sheet.
    const restore = useCallback((event: Event) => {
        const target = previous.current;
        previous.current = null;

        if (target && target.isConnected) {
            event.preventDefault();
            target.focus();
        }
    }, []);

    return { remember, restore };
}
