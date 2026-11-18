import { describe, expect, it } from 'vitest';
import {
    DEFAULT_TIMEZONE,
    formatDate,
    formatDay,
    fromLocalInput,
    toLocalInput,
} from '@/lib/dates';

describe('toLocalInput', () => {
    it('returns an empty string for missing dates', () => {
        expect(toLocalInput(null)).toBe('');
        expect(toLocalInput('')).toBe('');
    });

    it('returns an empty string for text that is not a date', () => {
        expect(toLocalInput('next Tuesday after lunch', 'Europe/Madrid')).toBe(
            '',
        );
    });

    it('uses UTC when no time zone is given', () => {
        expect(DEFAULT_TIMEZONE).toBe('UTC');
        expect(toLocalInput('2026-11-17T09:30:00Z')).toBe('2026-11-17T09:30');
    });

    it.each([
        ['Europe/Madrid', '2026-11-17T10:30'],
        ['America/New_York', '2026-11-17T04:30'],
        ['Asia/Tokyo', '2026-11-17T18:30'],
        ['Asia/Kolkata', '2026-11-17T15:00'],
        ['Asia/Kathmandu', '2026-11-17T15:15'],
        ['Australia/Adelaide', '2026-11-17T20:00'],
        ['Pacific/Chatham', '2026-11-17T23:15'],
    ])('shows 09:30 UTC as wall time in %s', (zone, expected) => {
        expect(toLocalInput('2026-11-17T09:30:00Z', zone)).toBe(expected);
    });

    it('moves to the next day when the zone is ahead of UTC', () => {
        expect(toLocalInput('2026-11-17T23:15:00Z', 'Asia/Tokyo')).toBe(
            '2026-11-18T08:15',
        );
    });

    it('moves to the previous day when the zone is behind UTC', () => {
        expect(toLocalInput('2026-11-17T05:00:00Z', 'Pacific/Honolulu')).toBe(
            '2026-11-16T19:00',
        );
    });

    it('writes midnight as 00 and never as 24', () => {
        expect(toLocalInput('2026-11-17T15:00:00Z', 'Asia/Tokyo')).toBe(
            '2026-11-18T00:00',
        );
    });

    it('follows daylight saving time in New York', () => {
        expect(toLocalInput('2026-03-08T06:30:00Z', 'America/New_York')).toBe(
            '2026-03-08T01:30',
        );
        expect(toLocalInput('2026-03-08T07:30:00Z', 'America/New_York')).toBe(
            '2026-03-08T03:30',
        );
    });

    it('follows daylight saving time in Madrid in summer', () => {
        expect(toLocalInput('2026-07-01T12:00:00Z', 'Europe/Madrid')).toBe(
            '2026-07-01T14:00',
        );
    });

    it('reads ISO strings that carry an offset', () => {
        expect(
            toLocalInput('2026-11-17T10:30:00+01:00', 'America/Los_Angeles'),
        ).toBe('2026-11-17T01:30');
    });

    it('rolls over the year at new year', () => {
        expect(toLocalInput('2026-12-31T23:30:00Z', 'Europe/Berlin')).toBe(
            '2027-01-01T00:30',
        );
    });
});

describe('fromLocalInput', () => {
    it('sends the typed wall time as it is', () => {
        expect(fromLocalInput('2026-11-20T08:00')).toBe('2026-11-20T08:00');
    });

    it('sends null when the field is empty', () => {
        expect(fromLocalInput('')).toBeNull();
    });
});

describe('formatDate', () => {
    it('returns an empty string for missing dates', () => {
        expect(formatDate(null)).toBe('');
        expect(formatDate('', 'Asia/Tokyo')).toBe('');
    });

    it('shows the same text as UTC shifted by the zone offset', () => {
        expect(formatDate('2026-11-17T08:30:00Z', 'Asia/Tokyo')).toBe(
            formatDate('2026-11-17T17:30:00Z', 'UTC'),
        );
        expect(formatDate('2026-11-17T08:30:00Z', 'America/Chicago')).toBe(
            formatDate('2026-11-17T02:30:00Z', 'UTC'),
        );
    });

    it('does not depend on the time zone of the computer', () => {
        expect(formatDate('2026-11-17T08:30:00Z')).toBe(
            new Intl.DateTimeFormat(undefined, {
                dateStyle: 'medium',
                timeStyle: 'short',
                timeZone: 'UTC',
            }).format(new Date('2026-11-17T08:30:00Z')),
        );
    });

    it('includes the year and the minutes', () => {
        const text = formatDate('2026-11-17T08:45:00Z', 'UTC');

        expect(text).toContain('2026');
        expect(text).toContain('45');
    });

    it('returns an empty string for text that is not a date', () => {
        expect(formatDate('sometime next spring', 'Europe/Madrid')).toBe('');
    });
});

describe('formatDay', () => {
    it('returns an empty string for missing dates', () => {
        expect(formatDay(null)).toBe('');
    });

    it('shows the next day in Tokyo for a late UTC evening', () => {
        expect(formatDay('2026-11-17T22:00:00Z', 'Asia/Tokyo')).toBe(
            formatDay('2026-11-18T12:00:00Z', 'UTC'),
        );
    });

    it('shows the previous day in Los Angeles for an early UTC morning', () => {
        expect(formatDay('2026-11-17T03:00:00Z', 'America/Los_Angeles')).toBe(
            formatDay('2026-11-16T12:00:00Z', 'UTC'),
        );
    });

    it('does not show the time', () => {
        expect(formatDay('2026-11-17T08:45:00Z', 'UTC')).not.toContain('45');
    });

    it('returns an empty string for text that is not a date', () => {
        expect(formatDay('the day after the launch', 'UTC')).toBe('');
    });
});
