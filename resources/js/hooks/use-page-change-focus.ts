import { router } from '@inertiajs/react';
import { useEffect } from 'react';

// Path of a page URL, without the query, so "?search=" changes do not count as a new page.
export function pathOf(url: string): string {
    try {
        return new URL(url, window.location.origin).pathname;
    } catch {
        return url.split(/[?#]/)[0] ?? url;
    }
}

// Moves focus to the main heading of the content area (or the area itself).
export function focusPageStart(container: HTMLElement): void {
    const heading = container.querySelector<HTMLElement>('h1');
    const target = heading ?? container;

    if (!target.hasAttribute('tabindex')) {
        target.setAttribute('tabindex', '-1');
    }

    target.focus({ preventScroll: true });
}

// After an Inertia visit to another page, focus moves to the new page heading, so screen reader users hear
// the page change. Visits that only change the query (search boxes, filters, sorting) keep the focus where it is.
export function usePageChangeFocus(containerId: string): void {
    useEffect(() => {
        let lastPath = pathOf(window.location.href);
        let frame = 0;

        const stop = router.on('navigate', (event) => {
            const url = (event as CustomEvent).detail?.page?.url;
            const path = typeof url === 'string' ? pathOf(url) : lastPath;

            if (path === lastPath) {
                return;
            }

            lastPath = path;
            window.cancelAnimationFrame(frame);
            // Wait one frame, so React has put the new page on the screen.
            frame = window.requestAnimationFrame(() => {
                const container = document.getElementById(containerId);
                const active = document.activeElement;

                // An open dialog keeps its own focus.
                if (!container || active?.closest('[role="dialog"]')) {
                    return;
                }

                focusPageStart(container);
            });
        });

        return () => {
            window.cancelAnimationFrame(frame);
            stop();
        };
    }, [containerId]);
}
