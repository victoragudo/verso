import { usePage } from '@inertiajs/react';

// Browser storage of the builder workspace. Keys hold the user id, so each person keeps their own sizes.
const PREFIX = 'verso.workspace';

export function workspaceKey(
    userId: number | string | null | undefined,
    name: string,
): string {
    return `${PREFIX}.${userId ?? 'guest'}.${name}`;
}

// The storage key for one choice of the signed in user.
export function useWorkspaceKey(name: string): string {
    const user = usePage().props.auth?.user as { id?: number } | null;

    return workspaceKey(user?.id, name);
}

// Storage can be blocked (private windows, strict settings), so reads give null on errors.
export function readStored(key: string): string | null {
    try {
        return window.localStorage.getItem(key);
    } catch {
        return null;
    }
}

export function writeStored(key: string, value: string | null): void {
    try {
        if (value === null) {
            window.localStorage.removeItem(key);
        } else {
            window.localStorage.setItem(key, value);
        }
    } catch {
        // The choice still works until the page is closed.
    }
}
