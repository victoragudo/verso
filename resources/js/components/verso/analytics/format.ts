import {
    formatCompact,
    formatDateTime,
    formatNumber,
    formatPercent,
} from '@/lib/i18n';

export { formatNumber, formatPercent };

// Days arrive as "2026-11-17". They are read as UTC so the label never shifts by one day.
function dayToDate(day: string): Date {
    return new Date(`${day}T00:00:00Z`);
}

// Axis ticks: in full under 10,000, compact above (12K).
export function formatTick(value: number, max: number): string {
    return max >= 10000 ? formatCompact(value) : formatNumber(value);
}

export function formatDay(
    day: string,
    options: Intl.DateTimeFormatOptions = { month: 'short', day: 'numeric' },
): string {
    return formatDateTime(dayToDate(day), { ...options, timeZone: 'UTC' });
}

// Clean round ticks from zero to a little above the largest value.
export function niceTicks(max: number, count = 4): number[] {
    if (max <= 0) {
        return [0, 1];
    }

    const rough = max / count;
    const power = 10 ** Math.floor(Math.log10(rough));
    const step =
        [1, 2, 5, 10]
            .map((factor) => factor * power)
            .find((candidate) => candidate >= rough) ?? rough;
    const niceStep = Math.max(1, step);
    const ticks: number[] = [];

    for (let tick = 0; tick < max + niceStep; tick += niceStep) {
        ticks.push(tick);

        if (tick >= max) {
            break;
        }
    }

    return ticks;
}
