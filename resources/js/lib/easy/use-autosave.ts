import { useCallback, useEffect, useRef, useState } from 'react';
import { msg, t } from '@/lib/i18n';
import type { Block } from '@/types';
import { clearDeviceDraft, writeDeviceDraft } from './page-drafts';
import { PageEditorError } from './page-requests';

// Keeps unsaved changes of the visual page editor as a draft a moment after the last change.
// On the server when it can, on this device when the internet is gone. The site never changes.

export type AutosaveStatus = 'idle' | 'saving' | 'saved' | 'offline' | 'error';

type Options = {
    entryId: number;
    blocks: Block[];
    // True while the blocks differ from the saved page.
    dirty: boolean;
    delay: number;
    // False while the person still decides about an older draft, so nothing is written or cleared.
    enabled?: boolean;
    save: (blocks: Block[], signal: AbortSignal) => Promise<unknown>;
    // Removes the server draft when the changes are undone back to the saved page.
    discard?: () => Promise<unknown>;
};

export const AUTOSAVE_LABELS: Record<AutosaveStatus, string> = {
    idle: '',
    saving: msg('Saving…'),
    saved: msg('All changes saved as a draft'),
    offline: msg('Offline, saved on this device'),
    error: msg('Not saved as a draft yet, your changes are still here'),
};

const isOffline = () =>
    typeof navigator !== 'undefined' && navigator.onLine === false;

export function useAutosave({
    entryId,
    blocks,
    dirty,
    delay,
    enabled = true,
    save,
    discard,
}: Options) {
    const [status, setStatus] = useState<AutosaveStatus>('idle');
    const [error, setError] = useState<string | null>(null);
    const saveRef = useRef(save);
    const discardRef = useRef(discard);
    const onServer = useRef(false);
    const latest = useRef({ blocks, dirty });

    useEffect(() => {
        saveRef.current = save;
        discardRef.current = discard;
        latest.current = { blocks, dirty };
    });

    const keepOnDevice = useCallback(
        (next: Block[], failure: string | null) => {
            writeDeviceDraft(entryId, next);
            setStatus(failure ? 'error' : 'offline');
            setError(failure);
        },
        [entryId],
    );

    // Sends the latest blocks now. Used by the timer and when the connection comes back.
    const run = useCallback(
        async (signal: AbortSignal) => {
            const { blocks: next, dirty: changed } = latest.current;

            if (!changed) {
                return;
            }

            if (isOffline()) {
                keepOnDevice(next, null);

                return;
            }

            setStatus('saving');

            try {
                await saveRef.current(next, signal);

                onServer.current = true;

                if (!signal.aborted) {
                    clearDeviceDraft(entryId);
                    setStatus('saved');
                    setError(null);
                }
            } catch (failure) {
                if (signal.aborted) {
                    return;
                }

                if (
                    failure instanceof PageEditorError &&
                    failure.kind !== 'offline'
                ) {
                    keepOnDevice(next, failure.message);
                } else {
                    keepOnDevice(next, null);
                }
            }
        },
        [entryId, keepOnDevice],
    );

    useEffect(() => {
        if (!enabled) {
            return;
        }

        if (!dirty) {
            clearDeviceDraft(entryId);

            if (onServer.current) {
                onServer.current = false;
                discardRef.current?.().catch(() => undefined);
            }

            setStatus('idle');
            setError(null);

            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(
            () => void run(controller.signal),
            delay,
        );

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [blocks, dirty, delay, entryId, enabled, run]);

    useEffect(() => {
        if (!enabled) {
            return;
        }

        const controller = new AbortController();
        const onOnline = () => void run(controller.signal);
        window.addEventListener('online', onOnline);

        return () => {
            window.removeEventListener('online', onOnline);
            controller.abort();
        };
    }, [enabled, run]);

    const label = AUTOSAVE_LABELS[status];

    return { status, error, label: label ? t(label) : '' };
}
