// Plain calendar math for the date pickers. A day has no time zone: it is
// what the wall clock shows in the site time zone.
import { currentLocale } from '@/lib/i18n';

export type CalendarDay = { year: number; month: number; day: number };

export type WallTime = { hour: number; minute: number };

const DAYS_IN_GRID = 42;
const MIDNIGHT: WallTime = { hour: 0, minute: 0 };
const EPOCH_DAY: CalendarDay = { year: 1970, month: 1, day: 1 };
const WALL_TIME_OPTIONS: Intl.DateTimeFormatOptions = {
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
};

const pad = (value: number): string => String(value).padStart(2, '0');

// A day as "YYYY-MM-DD".
export function dayKey(day: CalendarDay): string {
    return `${day.year}-${pad(day.month)}-${pad(day.day)}`;
}

// Time as "HH:mm".
export function timeKey(time: WallTime): string {
    return `${pad(time.hour)}:${pad(time.minute)}`;
}

// Reads "YYYY-MM-DD" (with or without a time after it).
export function parseDay(value: string | null | undefined): CalendarDay | null {
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value ?? '');

    if (!match) {
        return null;
    }

    const day = {
        year: Number(match[1]),
        month: Number(match[2]),
        day: Number(match[3]),
    };

    return day.month >= 1 &&
        day.month <= 12 &&
        day.day >= 1 &&
        day.day <= daysInMonth(day.year, day.month)
        ? day
        : null;
}

// Reads the "HH:mm" part of "YYYY-MM-DDTHH:mm".
export function parseTime(value: string | null | undefined): WallTime | null {
    const match = /T(\d{2}):(\d{2})/.exec(value ?? '');

    if (!match) {
        return null;
    }

    const hour = Number(match[1]);
    const minute = Number(match[2]);

    return hour < 24 && minute < 60 ? { hour, minute } : null;
}

// Builds the value a datetime-local input would send.
export function toDateTimeValue(day: CalendarDay, time: WallTime): string {
    return `${dayKey(day)}T${timeKey(time)}`;
}

export function daysInMonth(year: number, month: number): number {
    return new Date(Date.UTC(year, month, 0)).getUTCDate();
}

function toUtc(day: CalendarDay, time: WallTime = MIDNIGHT): Date {
    return new Date(
        Date.UTC(day.year, day.month - 1, day.day, time.hour, time.minute),
    );
}

function fromUtc(date: Date): CalendarDay {
    return {
        year: date.getUTCFullYear(),
        month: date.getUTCMonth() + 1,
        day: date.getUTCDate(),
    };
}

export function addDays(day: CalendarDay, amount: number): CalendarDay {
    const date = toUtc(day);
    date.setUTCDate(date.getUTCDate() + amount);

    return fromUtc(date);
}

// Moves by whole months and keeps the day inside the new month.
export function addMonths(day: CalendarDay, amount: number): CalendarDay {
    const index = day.year * 12 + (day.month - 1) + amount;
    const year = Math.floor(index / 12);
    const month = (index % 12) + 1;

    return {
        year,
        month,
        day: Math.min(day.day, daysInMonth(year, month)),
    };
}

// Day of the week with Monday as 0 and Sunday as 6.
export function weekdayIndex(day: CalendarDay): number {
    return (toUtc(day).getUTCDay() + 6) % 7;
}

export function startOfWeek(day: CalendarDay): CalendarDay {
    return addDays(day, -weekdayIndex(day));
}

export function endOfWeek(day: CalendarDay): CalendarDay {
    return addDays(day, 6 - weekdayIndex(day));
}

export function sameDay(a: CalendarDay | null, b: CalendarDay | null): boolean {
    return a !== null && b !== null && dayKey(a) === dayKey(b);
}

// The six weeks shown for a month, starting on a Monday.
export function monthGrid(year: number, month: number): CalendarDay[][] {
    const first = startOfWeek({ year, month, day: 1 });
    const weeks: CalendarDay[][] = [];

    for (let index = 0; index < DAYS_IN_GRID; index += 7) {
        weeks.push(
            Array.from({ length: 7 }, (_, offset) =>
                addDays(first, index + offset),
            ),
        );
    }

    return weeks;
}

// The next Monday after the given day (never the same day).
export function nextMonday(day: CalendarDay): CalendarDay {
    return addDays(day, 7 - weekdayIndex(day));
}

// Formats a wall-clock day with Intl without moving it to another zone.
export function formatCalendarDay(
    day: CalendarDay,
    options: Intl.DateTimeFormatOptions,
    locale: string = currentLocale(),
): string {
    return new Intl.DateTimeFormat(locale, {
        ...options,
        timeZone: 'UTC',
    }).format(toUtc(day));
}

export function formatCalendarDateTime(
    day: CalendarDay,
    time: WallTime,
    options: Intl.DateTimeFormatOptions,
    locale: string = currentLocale(),
): string {
    return new Intl.DateTimeFormat(locale, {
        ...options,
        ...WALL_TIME_OPTIONS,
        timeZone: 'UTC',
    }).format(toUtc(day, time));
}

export function formatWallTime(
    time: WallTime,
    locale: string = currentLocale(),
): string {
    return formatCalendarDateTime(EPOCH_DAY, time, {}, locale);
}
