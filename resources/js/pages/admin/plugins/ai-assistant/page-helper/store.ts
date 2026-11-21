import { markFocus, returnFocus } from '@/lib/easy/return-focus';
import type { FocusMark } from '@/lib/easy/return-focus';
import type { PageHelperProps, PageHelperRequest } from './types';

// Shared state of the page editor helper: the server props (set by the slot component)
// and the dialog that is open. The builder module and the dialogs both read it.
export type PageHelperState = {
    config: PageHelperProps | null;
    request: PageHelperRequest | null;
};

const empty: PageHelperState = { config: null, request: null };

let state: PageHelperState = empty;
// What had focus before the first helper dialog opened, given back when the last one closes.
let opener: FocusMark | null = null;
const listeners = new Set<() => void>();

function update(next: Partial<PageHelperState>): void {
    state = { ...state, ...next };
    listeners.forEach((listener) => listener());
}

export const pageHelper = {
    get: (): PageHelperState => state,
    subscribe: (listener: () => void): (() => void) => {
        listeners.add(listener);

        return () => listeners.delete(listener);
    },
    setConfig: (config: PageHelperProps | null) =>
        update(config ? { config } : { config: null, request: null }),
    // Keeps a new business description, so the next dialog starts with it.
    setBusiness(business: string): void {
        if (state.config) {
            update({ config: { ...state.config, business } });
        }
    },
    open(request: PageHelperRequest): void {
        if (!state.request) {
            opener = markFocus();
        }

        update({ request });
    },
    close: () => update({ request: null }),
    // Focuses the button that opened the helper again, once no helper dialog is open. False when it is gone.
    returnFocus(): boolean {
        if (state.request) {
            return false;
        }

        const done = returnFocus(opener);
        opener = null;

        return done;
    },
    reset(): void {
        opener = null;
        update(empty);
    },
};
