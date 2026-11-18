import { describe, expect, it } from 'vitest';
import { formatCalendarDateTime, formatWallTime } from '@/lib/calendar';
import { setMessages } from '@/lib/i18n';

const harborDay = { year: 2026, month: 11, day: 16 };
const ferryTime = { hour: 9, minute: 5 };

describe('formatWallTime', () => {
    it('shows a wall clock time in 24 hour format in the admin language', () => {
        expect(formatWallTime(ferryTime, 'en')).toBe('09:05');
        expect(formatWallTime({ hour: 21, minute: 30 }, 'de')).toBe('21:30');
    });

    it('uses the admin language by default', () => {
        setMessages('ar', {}, 'rtl');

        expect(formatWallTime(ferryTime)).toBe(formatWallTime(ferryTime, 'ar'));
    });
});

describe('formatCalendarDateTime', () => {
    it('joins the day and the time without moving them to another zone', () => {
        const text = formatCalendarDateTime(
            harborDay,
            ferryTime,
            { day: 'numeric', month: 'long', year: 'numeric' },
            'es',
        );

        expect(text).toContain('16 de noviembre de 2026');
        expect(text).toContain('09:05');
    });
});
