import { describe, expect, it } from 'vitest';
import {
    formatBytes,
    progressPercent,
    stepLabel,
    stepTone,
    updateStatusTone,
} from '@/components/verso/updates/update-status';
import { steps } from './fixtures';

describe('update status helpers', () => {
    it('picks a tone for every run status', () => {
        expect(updateStatusTone('completed')).toBe('success');
        expect(updateStatusTone('failed')).toBe('danger');
        expect(updateStatusTone('rolled_back')).toBe('warning');
        expect(updateStatusTone('running')).toBe('info');
        expect(updateStatusTone('pending')).toBe('neutral');
    });

    it('picks a tone and a label for every step status', () => {
        expect(stepTone('done')).toBe('success');
        expect(stepTone('warning')).toBe('warning');
        expect(stepTone('failed')).toBe('danger');
        expect(stepLabel('done')).toBe('Done');
        expect(stepLabel('warning')).toBe('Warning');
    });

    it('does not trust prototype keys', () => {
        expect(updateStatusTone('toString' as never)).toBe('neutral');
        expect(stepLabel('constructor' as never)).toBe('constructor');
    });

    it('counts finished steps and ignores the rollback step', () => {
        expect(progressPercent(steps(0))).toBe(0);
        expect(progressPercent(steps(13, 'done'))).toBe(100);
        expect(progressPercent(steps(5, 'warning'))).toBe(46);
        expect(
            progressPercent([
                ...steps(13, 'done'),
                { key: 'rollback', status: 'running' },
            ]),
        ).toBe(100);
        expect(progressPercent([])).toBe(0);
    });

    it('formats package sizes', () => {
        expect(formatBytes(18_350_112)).toBe('17.5 MB');
        expect(formatBytes(512)).toBe('512 byte');
        expect(formatBytes(2048)).toBe('2 kB');
        expect(formatBytes(null)).toBeNull();
        expect(formatBytes(-1)).toBeNull();
    });
});
