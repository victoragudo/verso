import { describe, expect, it, vi } from 'vitest';
import {
    errorKind,
    firstValidationMessage,
    messageForStatus,
    plainMessage,
    plainVisitErrors,
} from '@/lib/easy/plain-errors';

describe('plain error messages', () => {
    it.each([
        [null, 'offline'],
        [0, 'offline'],
        [401, 'session'],
        [419, 'session'],
        [403, 'forbidden'],
        [413, 'too-large'],
        [422, 'invalid'],
        [500, 'server'],
        [503, 'server'],
        [404, 'unknown'],
    ] as const)('names status %s as %s', (status, kind) => {
        expect(errorKind(status)).toBe(kind);
    });

    it('gives each status a plain sentence with a next step', () => {
        expect(messageForStatus(419)).toBe(
            'Your session ended. Sign in again in a new tab, then come back here and press Save.',
        );
        expect(messageForStatus(403)).toContain(
            'You do not have permission to do this.',
        );
        expect(messageForStatus(413)).toContain('too big');
        expect(messageForStatus(500)).toContain('Your changes are still here.');
        expect(messageForStatus(null)).toContain('You seem to be offline.');
        expect(messageForStatus(404)).toContain('That did not work.');
    });

    it('names the button to press again', () => {
        expect(plainMessage('session', { retry: 'Publish' })).toContain(
            'press Publish.',
        );
        expect(plainMessage('server', { retry: 'Build my site' })).toContain(
            'press Build my site.',
        );
    });

    it('shows the validation message for 422, or a plain fallback', () => {
        expect(
            plainMessage(
                'invalid',
                undefined,
                'Block 2: the link is not safe.',
            ),
        ).toBe('Block 2: the link is not safe.');
        expect(plainMessage('invalid')).toBe(
            'Something on the page is not right. Undo your last change, then press Save.',
        );
    });

    it('reads the first validation message, preferring a field', () => {
        expect(
            firstValidationMessage(
                { colors: 'Pick a color.', blocks: ['Block 1 is empty.'] },
                'blocks',
            ),
        ).toBe('Block 1 is empty.');
        expect(firstValidationMessage({ colors: 'Pick a color.' })).toBe(
            'Pick a color.',
        );
        expect(firstValidationMessage(undefined)).toBeUndefined();
    });

    it('turns Inertia http and network failures into one message and stops the error dialog', () => {
        const onMessage = vi.fn();
        const handlers = plainVisitErrors(onMessage, { retry: 'Save my look' });

        expect(handlers.onHttpException({ status: 419 })).toBe(false);
        expect(onMessage).toHaveBeenLastCalledWith(
            'Your session ended. Sign in again in a new tab, then come back here and press Save my look.',
        );
        expect(handlers.onNetworkError()).toBe(false);
        expect(onMessage).toHaveBeenLastCalledWith(
            expect.stringContaining('You seem to be offline.'),
        );
    });
});
