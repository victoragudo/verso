import { EmptyState } from '@/components/verso/ui';
import { t } from '@/lib/i18n';
import { formatNumber } from './format';
import type { RankedItem } from './types';

type Props = {
    items: RankedItem[];
    nameLabel: string;
    emptyIcon: string;
    emptyText: string;
};

// A short ranked table (top pages, top referrers) with a quiet bar behind each row.
export function RankedList({ items, nameLabel, emptyIcon, emptyText }: Props) {
    if (items.length === 0) {
        return (
            <EmptyState icon={emptyIcon} title={emptyText} className="py-10" />
        );
    }

    const max = Math.max(1, ...items.map((item) => item.views));

    return (
        <table className="w-full text-sm">
            <thead>
                <tr className="border-b text-xs text-muted-foreground">
                    <th
                        scope="col"
                        className="px-4 py-2 text-start font-medium sm:px-5"
                    >
                        {nameLabel}
                    </th>
                    <th scope="col" className="px-2 py-2 text-end font-medium">
                        {t('analytics::Visitors')}
                    </th>
                    <th
                        scope="col"
                        className="px-4 py-2 text-end font-medium sm:px-5"
                    >
                        {t('analytics::Views')}
                    </th>
                </tr>
            </thead>
            <tbody className="divide-y">
                {items.map((item) => (
                    <tr key={item.value} className="hover:bg-muted/40">
                        <th
                            scope="row"
                            className="relative max-w-0 px-4 py-2.5 text-start font-normal sm:px-5"
                        >
                            <span
                                aria-hidden="true"
                                className="absolute inset-y-1.5 start-2 rounded bg-brand-soft sm:start-3"
                                style={{
                                    width: `calc(${(item.views / max) * 100}% - 1rem)`,
                                }}
                            />
                            <span
                                className="relative block truncate text-foreground"
                                title={item.value}
                            >
                                {item.value}
                            </span>
                        </th>
                        <td className="w-20 px-2 py-2.5 text-end text-muted-foreground tabular-nums">
                            {formatNumber(item.visitors)}
                        </td>
                        <td className="w-20 px-4 py-2.5 text-end font-medium text-foreground tabular-nums sm:px-5">
                            {formatNumber(item.views)}
                        </td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}
