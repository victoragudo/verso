// Date helpers for admin forms and lists. Dates come from the server in UTC
// and are shown in the site time zone (the site.timezone setting).

import { currentLocale } from '@/lib/i18n';

// Used when no time zone is known yet.
export const DEFAULT_TIMEZONE = 'UTC';

// The parts of a date as seen on a wall clock in one time zone.
function zonedParts(date: Date, timeZone: string): Record<string, string> {
    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).formatToParts(date);

    return Object.fromEntries(parts.map((part) => [part.type, part.value]));
}

// The date of an ISO string, or null when it is empty or not a date.
function parseDate(iso: string | null): Date | null {
    if (!iso) {
        return null;
    }

    const date = new Date(iso);

    return Number.isNaN(date.getTime()) ? null : date;
}

// ISO string to the value of a datetime-local input, in the site time zone.
export function toLocalInput(
    iso: string | null,
    timeZone: string = DEFAULT_TIMEZONE,
): string {
    const date = parseDate(iso);

    if (!date) {
        return '';
    }

    const parts = zonedParts(date, timeZone);

    return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`;
}

// Value of a datetime-local input, sent as it is. The server reads it in the
// site time zone and stores it in UTC.
export function fromLocalInput(value: string): string | null {
    return value ? value : null;
}

// A short date and time in the site time zone, in the admin language, like "Nov 17, 2026, 9:30 AM".
export function formatDate(
    iso: string | null,
    timeZone: string = DEFAULT_TIMEZONE,
): string {
    const date = parseDate(iso);

    if (!date) {
        return '';
    }

    return new Intl.DateTimeFormat(currentLocale(), {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone,
    }).format(date);
}

// Only the day, like "Nov 17, 2026", in the site time zone.
export function formatDay(
    iso: string | null,
    timeZone: string = DEFAULT_TIMEZONE,
): string {
    const date = parseDate(iso);

    if (!date) {
        return '';
    }

    return new Intl.DateTimeFormat(currentLocale(), {
        dateStyle: 'medium',
        timeZone,
    }).format(date);
}
