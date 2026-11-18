import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    clearDeviceDraft,
    draftKey,
    draftTime,
    pickDraft,
    readDeviceDraft,
    writeDeviceDraft,
} from '@/lib/easy/page-drafts';
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

const saved = heading('Fresh bread on Harbour Lane');
const page = { blocks: saved, updatedAt: '2026-11-18T09:00:00Z' };

beforeEach(() => window.localStorage.clear());
afterEach(() => vi.restoreAllMocks());

describe('page drafts', () => {
    it('keeps a copy on this device and reads it back', () => {
        expect(
            writeDeviceDraft(
                12,
                heading('Sourdough on Saturdays'),
                '2026-11-18T10:00:00Z',
            ),
        ).toBe(true);
        expect(readDeviceDraft(12)).toEqual({
            blocks: heading('Sourdough on Saturdays'),
            savedAt: '2026-11-18T10:00:00Z',
        });

        clearDeviceDraft(12);
        expect(readDeviceDraft(12)).toBeNull();
    });

    it('works without storage and ignores broken copies', () => {
        window.localStorage.setItem(draftKey(12), '{not json');
        expect(readDeviceDraft(12)).toBeNull();

        vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
            throw new DOMException('Quota', 'QuotaExceededError');
        });
        expect(writeDeviceDraft(12, saved)).toBe(false);
        expect(() => clearDeviceDraft(12)).not.toThrow();
    });

    it('picks the newest draft that is newer than the page and differs from it', () => {
        const server = {
            blocks: heading('From the server'),
            savedAt: '2026-11-18T10:00:00Z',
        };
        const device = {
            blocks: heading('From this device'),
            savedAt: '2026-11-18T11:00:00Z',
        };

        expect(pickDraft(server, device, page)).toEqual({
            ...device,
            source: 'device',
        });
        expect(pickDraft(server, null, page)).toEqual({
            ...server,
            source: 'server',
        });
        expect(
            pickDraft(
                { ...server, savedAt: '2026-11-18T08:00:00Z' },
                null,
                page,
            ),
        ).toBeNull();
        expect(
            pickDraft(
                { blocks: saved, savedAt: '2026-11-18T10:00:00Z' },
                null,
                page,
            ),
        ).toBeNull();
        expect(pickDraft(null, null, page)).toBeNull();
    });

    it('shows the time for today and the day for older drafts', () => {
        const now = new Date('2026-11-18T15:00:00');

        expect(draftTime('2026-11-18T14:32:00', now)).toMatch(/14:32|2:32/);
        expect(draftTime('2026-11-17T14:32:00', now)).toMatch(/17/);
        expect(draftTime('not a date', now)).toBe('');
    });
});
