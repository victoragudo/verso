import { describe, expect, it } from 'vitest';
import {
    bestUnit,
    formatBytes,
    formatValue,
    fromDisplay,
    rangeProblem,
    serverUploadLimit,
    toDisplay,
    unitChoices,
} from '@/components/verso/system/units';

describe('system setting units', () => {
    it('offers the stored unit and bigger ones of the same kind', () => {
        expect(unitChoices('kb')).toEqual(['kb', 'mb', 'gb']);
        expect(unitChoices('seconds')).toEqual([
            'seconds',
            'minutes',
            'hours',
            'days',
        ]);
        expect(unitChoices('items')).toEqual(['items']);
    });

    it('converts between the stored and the shown unit', () => {
        expect(toDisplay(10240, 'kb', 'mb')).toBe(10);
        expect(toDisplay(204800, 'kb', 'gb')).toBe(0.2);
        expect(fromDisplay(64, 'kb', 'mb')).toBe(65536);
        expect(fromDisplay(1.5, 'seconds', 'hours')).toBe(5400);
    });

    it('picks the biggest unit that shows a whole number', () => {
        expect(bestUnit(10240, 'kb')).toBe('mb');
        expect(bestUnit(1000, 'kb')).toBe('kb');
        expect(bestUnit(86400, 'seconds')).toBe('days');
        expect(formatValue(3600, 'seconds')).toBe('1 hour');
        expect(formatValue(80, 'percent')).toBe('80%');
        expect(formatValue(200, 'items')).toBe('200 items');
    });

    it('formats bytes and finds the lowest server limit', () => {
        expect(formatBytes(7.5 * 1024 ** 2)).toBe('7.5 MB');
        expect(
            serverUploadLimit([
                { key: 'verso', bytes: 1024 },
                { key: 'upload_max_filesize', bytes: 8 * 1024 ** 2 },
                { key: 'post_max_size', bytes: 7 * 1024 ** 2 },
                { key: 'web_server', bytes: null },
            ]),
        ).toEqual({ key: 'post_max_size', bytes: 7 * 1024 ** 2 });
        expect(serverUploadLimit([{ key: 'verso', bytes: 1 }])).toBeNull();
    });

    it('explains values out of range in the words of the server', () => {
        expect(rangeProblem(5000, 10, 200, 'items')).toBe(
            'Use 200 items or less.',
        );
        expect(rangeProblem(2, 10, 200, 'items')).toBe('Use 10 items or more.');
        expect(rangeProblem(Number.NaN, 10, 200, 'items')).toBe(
            'Enter a number.',
        );
        expect(rangeProblem(50, 10, 200, 'items')).toBeNull();
    });
});
