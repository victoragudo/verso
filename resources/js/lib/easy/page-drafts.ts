import { formatDateTime } from '@/lib/i18n';
import type { Block } from '@/types';

// Automatic drafts of the visual page editor: a copy on this device for when the internet is gone,
// and the choice between the server draft, the device copy and the saved page when the editor opens.

export type DraftCopy = { blocks: Block[]; savedAt: string };

export type FoundDraft = DraftCopy & { source: 'server' | 'device' };

const KEY_PREFIX = 'verso-easy-draft:';

export function draftKey(entryId: number): string {
    return `${KEY_PREFIX}${entryId}`;
}

// Storage can be missing or blocked (private windows, previews), so every call is wrapped.
function storage(): Storage | null {
    try {
        return window.localStorage;
    } catch {
        return null;
    }
}

export function readDeviceDraft(entryId: number): DraftCopy | null {
    try {
        const raw = storage()?.getItem(draftKey(entryId));

        if (!raw) {
            return null;
        }

        const parsed = JSON.parse(raw) as Partial<DraftCopy>;

        return Array.isArray(parsed.blocks) &&
            typeof parsed.savedAt === 'string'
            ? { blocks: parsed.blocks, savedAt: parsed.savedAt }
            : null;
    } catch {
        return null;
    }
}

// Keeps a copy on this device. Returns false when the browser does not allow it.
export function writeDeviceDraft(
    entryId: number,
    blocks: Block[],
    savedAt: string = new Date().toISOString(),
): boolean {
    try {
        const store = storage();

        if (!store) {
            return false;
        }

        store.setItem(draftKey(entryId), JSON.stringify({ blocks, savedAt }));

        return true;
    } catch {
        return false;
    }
}

export function clearDeviceDraft(entryId: number): void {
    try {
        storage()?.removeItem(draftKey(entryId));
    } catch {
        // Nothing to clear when storage is blocked.
    }
}

const time = (iso: string | null | undefined): number => {
    const value = iso ? Date.parse(iso) : NaN;

    return Number.isNaN(value) ? 0 : value;
};

const sameBlocks = (a: Block[], b: Block[]) =>
    JSON.stringify(a) === JSON.stringify(b);

// The newest draft that is newer than the saved page and differs from it, or null.
export function pickDraft(
    server: { blocks: Block[]; savedAt: string | null } | null,
    device: DraftCopy | null,
    page: { blocks: Block[]; updatedAt: string | null },
): FoundDraft | null {
    const candidates: FoundDraft[] = [];

    if (server?.savedAt) {
        candidates.push({
            blocks: server.blocks,
            savedAt: server.savedAt,
            source: 'server',
        });
    }

    if (device) {
        candidates.push({ ...device, source: 'device' });
    }

    const pageTime = time(page.updatedAt);
    const newest = candidates
        .filter((draft) => time(draft.savedAt) > pageTime)
        .filter((draft) => !sameBlocks(draft.blocks, page.blocks))
        .sort((a, b) => time(b.savedAt) - time(a.savedAt))[0];

    return newest ?? null;
}

// "14:32" for today, "17 Nov, 14:32" for another day, in the admin language. Empty for a bad date.
export function draftTime(iso: string, now: Date = new Date()): string {
    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const today = date.toDateString() === now.toDateString();

    return formatDateTime(date, {
        ...(today ? {} : { day: 'numeric', month: 'short' }),
        hour: '2-digit',
        minute: '2-digit',
    });
}
