import { act, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { readDeviceDraft, writeDeviceDraft } from '@/lib/easy/page-drafts';
import { PageEditorError } from '@/lib/easy/page-requests';
import { useAutosave } from '@/lib/easy/use-autosave';
import type { Block } from '@/types';

const heading = (text: string): Block[] => [
    {
        id: 'b_hero0001',
        type: 'heading',
        attrs: { level: 1 },
        content: text,
        children: [],
    },
];

type Props = { blocks: Block[]; dirty: boolean; enabled?: boolean };

function setup(save: (blocks: Block[]) => Promise<unknown>, initial: Props) {
    return renderHook(
        (props: Props) =>
            useAutosave({ entryId: 12, delay: 2000, save, ...props }),
        { initialProps: initial },
    );
}

const setOnline = (online: boolean) =>
    Object.defineProperty(window.navigator, 'onLine', {
        value: online,
        configurable: true,
    });

beforeEach(() => {
    vi.useFakeTimers();
    window.localStorage.clear();
    setOnline(true);
});

afterEach(() => {
    vi.useRealTimers();
    setOnline(true);
});

describe('autosave', () => {
    it('saves a draft once, a moment after the last change', async () => {
        const save = vi.fn().mockResolvedValue({});
        const { result, rerender } = setup(save, {
            blocks: heading('Fresh bread'),
            dirty: false,
        });

        rerender({ blocks: heading('Fresh bread daily'), dirty: true });
        await act(() => vi.advanceTimersByTimeAsync(1500));
        rerender({ blocks: heading('Fresh bread every day'), dirty: true });
        await act(() => vi.advanceTimersByTimeAsync(1500));
        expect(save).not.toHaveBeenCalled();

        await act(() => vi.advanceTimersByTimeAsync(600));
        expect(save).toHaveBeenCalledTimes(1);
        expect(save.mock.calls[0][0]).toEqual(heading('Fresh bread every day'));
        expect(result.current.status).toBe('saved');
        expect(result.current.label).toBe('All changes saved as a draft');
    });

    it('keeps the changes on this device when offline and sends them when the connection is back', async () => {
        const save = vi.fn().mockResolvedValue({});
        const { result, rerender } = setup(save, {
            blocks: heading('Fresh bread'),
            dirty: false,
        });
        setOnline(false);

        rerender({ blocks: heading('Closed on Monday'), dirty: true });
        await act(() => vi.advanceTimersByTimeAsync(2000));

        expect(save).not.toHaveBeenCalled();
        expect(result.current.label).toBe('Offline, saved on this device');
        expect(readDeviceDraft(12)?.blocks).toEqual(
            heading('Closed on Monday'),
        );

        setOnline(true);
        await act(async () => {
            window.dispatchEvent(new Event('online'));
            await vi.advanceTimersByTimeAsync(0);
        });

        expect(save).toHaveBeenCalledTimes(1);
        expect(result.current.status).toBe('saved');
        expect(readDeviceDraft(12)).toBeNull();
    });

    it('falls back to this device when the request fails', async () => {
        const save = vi
            .fn()
            .mockRejectedValueOnce(
                new PageEditorError('You seem to be offline.', 'offline'),
            )
            .mockRejectedValueOnce(
                new PageEditorError('Your session ended.', 'session'),
            );
        const { result, rerender } = setup(save, {
            blocks: heading('Fresh bread'),
            dirty: false,
        });

        rerender({ blocks: heading('Open late on Friday'), dirty: true });
        await act(() => vi.advanceTimersByTimeAsync(2000));
        expect(result.current.status).toBe('offline');
        expect(readDeviceDraft(12)?.blocks).toEqual(
            heading('Open late on Friday'),
        );

        rerender({ blocks: heading('Open late on Saturday'), dirty: true });
        await act(() => vi.advanceTimersByTimeAsync(2000));
        expect(result.current.status).toBe('error');
        expect(result.current.error).toBe('Your session ended.');
        expect(readDeviceDraft(12)?.blocks).toEqual(
            heading('Open late on Saturday'),
        );
    });

    it('does nothing while the person decides about an older draft, and clears the copy after a save', async () => {
        const save = vi.fn().mockResolvedValue({});
        writeDeviceDraft(12, heading('Older change'));
        const { result, rerender } = setup(save, {
            blocks: heading('Fresh bread'),
            dirty: false,
            enabled: false,
        });

        await act(() => vi.advanceTimersByTimeAsync(5000));
        expect(readDeviceDraft(12)).not.toBeNull();

        rerender({
            blocks: heading('Fresh bread'),
            dirty: false,
            enabled: true,
        });
        expect(readDeviceDraft(12)).toBeNull();
        expect(result.current.status).toBe('idle');
        expect(save).not.toHaveBeenCalled();
    });

    it('removes the server draft when the changes are undone back to the saved page', async () => {
        const save = vi.fn().mockResolvedValue({});
        const discard = vi.fn().mockResolvedValue({});
        const { rerender } = renderHook(
            (props: Props) =>
                useAutosave({
                    entryId: 12,
                    delay: 2000,
                    save,
                    discard,
                    ...props,
                }),
            {
                initialProps: {
                    blocks: heading('Fresh bread'),
                    dirty: false,
                } as Props,
            },
        );
        expect(discard).not.toHaveBeenCalled();

        rerender({ blocks: heading('Fresh rye'), dirty: true });
        await act(() => vi.advanceTimersByTimeAsync(2000));
        expect(save).toHaveBeenCalledTimes(1);

        rerender({ blocks: heading('Fresh bread'), dirty: false });
        expect(discard).toHaveBeenCalledTimes(1);
    });
});
