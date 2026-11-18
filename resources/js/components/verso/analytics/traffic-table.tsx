import { t } from '@/lib/i18n';
import { formatDay, formatNumber } from './format';
import type { TrafficPoint } from './types';

// The chart data as a plain table, newest day first.
export function TrafficTable({ points }: { points: TrafficPoint[] }) {
    return (
        <div className="max-h-80 overflow-y-auto rounded-md border">
            <table className="w-full text-sm">
                <thead className="sticky top-0 bg-muted text-xs text-muted-foreground">
                    <tr>
                        <th
                            scope="col"
                            className="px-3 py-2 text-start font-medium"
                        >
                            {t('analytics::Day')}
                        </th>
                        <th
                            scope="col"
                            className="px-3 py-2 text-end font-medium"
                        >
                            {t('analytics::Visitors')}
                        </th>
                        <th
                            scope="col"
                            className="px-3 py-2 text-end font-medium"
                        >
                            {t('analytics::Page views')}
                        </th>
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {points.toReversed().map((point) => (
                        <tr key={point.date}>
                            <th
                                scope="row"
                                className="px-3 py-2 text-start font-normal text-foreground"
                            >
                                {formatDay(point.date, {
                                    weekday: 'short',
                                    month: 'short',
                                    day: 'numeric',
                                    year: 'numeric',
                                })}
                            </th>
                            <td className="px-3 py-2 text-end tabular-nums">
                                {formatNumber(point.visitors)}
                            </td>
                            <td className="px-3 py-2 text-end tabular-nums">
                                {formatNumber(point.views)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
