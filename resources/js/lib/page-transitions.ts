import { router } from '@inertiajs/react';
import type { PendingVisit } from '@inertiajs/core';

const REDUCED_MOTION = '(prefers-reduced-motion: reduce)';

// True for a visit that opens another admin page: a GET to a new path that
// does not keep the page state. Query only visits (search, filters, paging)
// and partial reloads stay instant.
export function opensAnotherPage(
    visit: Pick<
        PendingVisit,
        'method' | 'url' | 'preserveState' | 'only' | 'prefetch'
    >,
    current: URL,
): boolean {
    return (
        visit.method === 'get' &&
        !visit.prefetch &&
        visit.preserveState !== true &&
        visit.only.length === 0 &&
        visit.url.pathname !== current.pathname
    );
}

// Admin page changes cross fade with a same document view transition, only
// when the browser has it and the person did not ask for less motion.
export function enablePageTransitions(): () => void {
    return router.on('before', (event) => {
        const visit = event.detail.visit;

        if (
            typeof document.startViewTransition !== 'function' ||
            window.matchMedia(REDUCED_MOTION).matches ||
            !opensAnotherPage(visit, new URL(window.location.href))
        ) {
            return;
        }

        visit.viewTransition = true;
    });
}
