import type { CheckOutcome } from '../types';

export const good = (message: string): CheckOutcome => ({
    status: 'good',
    message,
});

export const ok = (message: string): CheckOutcome => ({
    status: 'ok',
    message,
});

export const problem = (message: string): CheckOutcome => ({
    status: 'problem',
    message,
});

// A percent with at most one decimal, like 12.5.
export function percent(part: number, total: number): number {
    return total === 0 ? 0 : Math.round((part / total) * 1000) / 10;
}
