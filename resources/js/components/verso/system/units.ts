// Unit helpers for System settings. Values are stored in a base unit (like KB)
// and people can type them in a bigger one (like MB).
import { formatNumber, msg, t } from '@/lib/i18n';

export { formatBytes } from '@/lib/i18n';

const FACTORS: Record<string, number> = {
    bytes: 1,
    kb: 1024,
    mb: 1024 ** 2,
    gb: 1024 ** 3,
    ms: 1,
    seconds: 1000,
    minutes: 60_000,
    hours: 3_600_000,
    days: 86_400_000,
};

const LABELS: Record<string, string> = {
    bytes: msg('bytes'),
    kb: msg('KB'),
    mb: msg('MB'),
    gb: msg('GB'),
    ms: msg('ms'),
    seconds: msg('seconds'),
    minutes: msg('minutes'),
    hours: msg('hours'),
    days: msg('days'),
    items: msg('items'),
    characters: msg('characters'),
    pixels: msg('pixels'),
    percent: '%',
    requests: msg('requests'),
    none: '',
};

const AMOUNTS: Record<string, string> = {
    bytes: msg('{count, plural, one {# byte} other {# bytes}}'),
    kb: msg('{count} KB'),
    mb: msg('{count} MB'),
    gb: msg('{count} GB'),
    ms: msg('{count} ms'),
    seconds: msg('{count, plural, one {# second} other {# seconds}}'),
    minutes: msg('{count, plural, one {# minute} other {# minutes}}'),
    hours: msg('{count, plural, one {# hour} other {# hours}}'),
    days: msg('{count, plural, one {# day} other {# days}}'),
    items: msg('{count, plural, one {# item} other {# items}}'),
    characters: msg('{count, plural, one {# character} other {# characters}}'),
    pixels: msg('{count, plural, one {# pixel} other {# pixels}}'),
    requests: msg('{count, plural, one {# request} other {# requests}}'),
};

const PERCENT_SCALE = 100;

const SIZE_UNITS = ['bytes', 'kb', 'mb', 'gb'];
const TIME_UNITS = ['ms', 'seconds', 'minutes', 'hours', 'days'];

// Short name of a unit, like "MB".
export function unitLabel(unit: string): string {
    const label = LABELS[unit];

    return label === undefined ? unit : label === '' ? '' : t(label);
}

// Units a value can be typed in: the stored unit and the bigger ones of the same kind.
export function unitChoices(unit: string): string[] {
    const list = SIZE_UNITS.includes(unit)
        ? SIZE_UNITS
        : TIME_UNITS.includes(unit)
          ? TIME_UNITS
          : [unit];
    const start = list.indexOf(unit);

    return list.slice(start, start + 4);
}

function factor(unit: string): number {
    return FACTORS[unit] ?? 1;
}

// A stored value shown in another unit, with at most two decimals.
export function toDisplay(
    value: number,
    unit: string,
    display: string,
): number {
    return Math.round((value * factor(unit) * 100) / factor(display)) / 100;
}

// A typed value turned back into the stored unit (whole numbers only).
export function fromDisplay(
    value: number,
    unit: string,
    display: string,
): number {
    return Math.round((value * factor(display)) / factor(unit));
}

// The biggest unit that shows the value as a whole number, like 10240 KB as 10 MB.
export function bestUnit(value: number, unit: string): string {
    const choices = unitChoices(unit);

    for (let index = choices.length - 1; index > 0; index--) {
        const shown = (value * factor(unit)) / factor(choices[index]);

        if (value !== 0 && Number.isInteger(shown)) {
            return choices[index];
        }
    }

    return unit;
}

// A value with its unit for people, like "10 MB" or "2 hours".
export function formatValue(value: number, unit: string): string {
    const shown = bestUnit(value, unit);
    const count = toDisplay(value, unit, shown);

    if (shown === 'percent') {
        return formatNumber(count / PERCENT_SCALE, {
            style: 'percent',
            maximumFractionDigits: 2,
        });
    }

    const amount = AMOUNTS[shown];

    if (amount !== undefined) {
        return t(amount, { count });
    }

    const label = unitLabel(shown);
    const number = formatNumber(count);

    return label === '' ? number : `${number} ${label}`;
}

// The lowest PHP or web server limit for an upload, or null when none is known.
export function serverUploadLimit(
    parts: { key: string; bytes: number | null }[],
): { key: string; bytes: number } | null {
    let lowest: { key: string; bytes: number } | null = null;

    for (const part of parts) {
        if (part.key === 'verso' || part.bytes === null) {
            continue;
        }

        if (lowest === null || part.bytes < lowest.bytes) {
            lowest = { key: part.key, bytes: part.bytes };
        }
    }

    return lowest;
}

// Why a typed number cannot be saved, or null. Same wording as the server.
export function rangeProblem(
    value: number,
    min: number | null,
    max: number | null,
    unit: string,
): string | null {
    if (Number.isNaN(value)) {
        return t('Enter a number.');
    }

    if (min !== null && value < min) {
        return t('Use {value} or more.', { value: formatValue(min, unit) });
    }

    if (max !== null && value > max) {
        return t('Use {value} or less.', { value: formatValue(max, unit) });
    }

    return null;
}
