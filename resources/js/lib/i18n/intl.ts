// Numbers, dates, relative times, lists and sizes in the admin language.
import { currentLocale } from './store';

const formats = new Map<string, Intl.NumberFormat | Intl.DateTimeFormat>();

function cached<T extends Intl.NumberFormat | Intl.DateTimeFormat>(
    key: string,
    create: () => T,
): T {
    const fullKey = `${currentLocale()}|${key}`;
    let format = formats.get(fullKey);

    if (!format) {
        format = create();
        formats.set(fullKey, format);
    }

    return format as T;
}

export function formatNumber(
    value: number,
    options: Intl.NumberFormatOptions = {},
): string {
    return cached(
        `n${JSON.stringify(options)}`,
        () => new Intl.NumberFormat(currentLocale(), options),
    ).format(value);
}

// A share from 0 to 1, like 0.25 = "25%".
export function formatPercent(
    value: number,
    maximumFractionDigits = 1,
): string {
    return formatNumber(value, { style: 'percent', maximumFractionDigits });
}

// Short numbers like "12K" for charts and cards.
export function formatCompact(value: number): string {
    return formatNumber(value, {
        notation: 'compact',
        maximumFractionDigits: 1,
    });
}

// Money in the minor or major unit. Pass minor amounts with fromMinor.
export function formatCurrency(
    amount: number,
    currency: string,
    options: Intl.NumberFormatOptions = {},
): string {
    try {
        return formatNumber(amount, {
            style: 'currency',
            currency,
            ...options,
        });
    } catch {
        return `${formatNumber(amount, { minimumFractionDigits: 2 })} ${currency}`;
    }
}

export function formatDateTime(
    value: Date | string | number,
    options: Intl.DateTimeFormatOptions = {
        dateStyle: 'medium',
        timeStyle: 'short',
    },
): string {
    const date = value instanceof Date ? value : new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return cached(
        `d${JSON.stringify(options)}`,
        () => new Intl.DateTimeFormat(currentLocale(), options),
    ).format(date);
}

const RELATIVE_STEPS: [Intl.RelativeTimeFormatUnit, number][] = [
    ['second', 60],
    ['minute', 60],
    ['hour', 24],
    ['day', 7],
    ['week', 4.34524],
    ['month', 12],
    ['year', Number.POSITIVE_INFINITY],
];

// "3 minutes ago", "in 2 days", "yesterday".
export function formatRelativeTime(
    value: Date | string | number,
    now: Date = new Date(),
): string {
    const date = value instanceof Date ? value : new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    let amount = (date.getTime() - now.getTime()) / 1000;

    for (const [unit, size] of RELATIVE_STEPS) {
        if (Math.abs(amount) < size) {
            return new Intl.RelativeTimeFormat(currentLocale(), {
                numeric: 'auto',
            }).format(Math.round(amount), unit);
        }

        amount /= size;
    }

    return '';
}

export function formatList(
    items: string[],
    type: Intl.ListFormatType = 'conjunction',
): string {
    return new Intl.ListFormat(currentLocale(), { style: 'long', type }).format(
        items,
    );
}

const BYTE_UNITS = ['byte', 'kilobyte', 'megabyte', 'gigabyte', 'terabyte'];
const BYTE_STEP = 1024;

// File sizes like "1.5 MB", with the unit name of the admin language.
export function formatBytes(bytes: number): string {
    let value = Math.max(0, bytes);
    let unit = 0;

    while (value >= BYTE_STEP && unit < BYTE_UNITS.length - 1) {
        value /= BYTE_STEP;
        unit++;
    }

    return formatNumber(value, {
        style: 'unit',
        unit: BYTE_UNITS[unit],
        unitDisplay: 'short',
        maximumFractionDigits: unit === 0 ? 0 : 1,
    });
}

// The name of a language in the admin language, like "German" or "Alemán".
export function languageName(code: string): string {
    try {
        return (
            new Intl.DisplayNames([currentLocale()], { type: 'language' }).of(
                code.replaceAll('_', '-'),
            ) ?? code
        );
    } catch {
        return code;
    }
}
