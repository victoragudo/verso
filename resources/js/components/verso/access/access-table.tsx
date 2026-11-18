import { useId } from 'react';
import type { ReactNode } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import type { CheckState } from '@/lib/access';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { Capability } from '@/types';
import { RiskBadge } from './risk-badge';

export const HEAD_CELL =
    'sticky top-0 z-20 border-b bg-card px-3 py-2 align-bottom font-medium text-foreground';
export const CORNER_CELL =
    'sticky top-0 start-0 z-30 border-e border-b bg-card px-3 py-2 text-start align-bottom font-medium text-foreground';
export const ROW_HEAD_CELL =
    'sticky start-0 z-10 border-e border-b bg-card px-3 py-2 text-start align-top font-normal';
export const BODY_CELL = 'border-b px-2 py-1 text-center align-middle';
export const FIRST_COLUMN_WIDTH =
    'w-56 min-w-56 @3xl/page:w-80 @3xl/page:min-w-80';

type TableProps = {
    caption: string;
    children: ReactNode;
    className?: string;
};

// A scrolling table region with a sticky header row and a sticky first column.
export function AccessTable({ caption, children, className }: TableProps) {
    const captionId = useId();

    return (
        <div
            role="region"
            aria-labelledby={captionId}
            tabIndex={0}
            className={cn(
                'relative max-h-[min(78svh,64rem)] max-w-full overflow-auto overscroll-x-contain rounded-lg border bg-card shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                className,
            )}
        >
            <table className="w-full border-separate border-spacing-0 text-sm">
                <caption id={captionId} className="sr-only">
                    {caption}
                </caption>
                {children}
            </table>
        </div>
    );
}

// A checkbox inside a 40px box, so it is easy to hit on touch screens.
export function TouchCheckbox({
    label,
    checked,
    disabled,
    title,
    onChange,
}: {
    label: string;
    checked: CheckState;
    disabled?: boolean;
    title?: string;
    onChange: (checked: boolean) => void;
}) {
    return (
        <span
            title={title}
            className="inline-flex size-10 items-center justify-center rounded-md has-[button:enabled]:hover:bg-accent"
        >
            <Checkbox
                aria-label={label}
                checked={checked}
                disabled={disabled}
                onCheckedChange={(value) => onChange(value === true)}
                className="size-[1.125rem]"
            />
        </span>
    );
}

// The sticky first cell of a capability row: label, risk, description and what includes it.
export function CapabilityHeader({
    capability,
    includedIn,
}: {
    capability: Capability;
    includedIn?: string;
}) {
    return (
        <th scope="row" className={cn(ROW_HEAD_CELL, FIRST_COLUMN_WIDTH)}>
            <span className="flex flex-wrap items-center gap-x-2 gap-y-1">
                <span className="font-medium text-foreground">
                    {capability.label}
                </span>
                <RiskBadge risk={capability.risk} />
            </span>
            {capability.description && (
                <span className="mt-0.5 line-clamp-2 block text-xs text-muted-foreground">
                    {capability.description}
                </span>
            )}
            {includedIn && (
                <span className="mt-0.5 block text-xs text-muted-foreground italic">
                    {t('Included in {roles}', { roles: includedIn })}
                </span>
            )}
        </th>
    );
}

// A full width row that names a group or a section of capabilities.
export function HeadingRow({
    title,
    columns,
    level,
    children,
}: {
    title: string;
    columns: number;
    level: 'group' | 'section';
    children?: ReactNode;
}) {
    const isGroup = level === 'group';

    return (
        <tr className={isGroup ? 'bg-muted' : undefined}>
            <th
                scope="rowgroup"
                className={cn(
                    ROW_HEAD_CELL,
                    'align-middle',
                    isGroup
                        ? 'bg-muted py-2.5 text-xs font-semibold tracking-wide text-foreground uppercase'
                        : 'bg-card pt-3 pb-1.5 text-xs font-semibold text-muted-foreground',
                )}
            >
                {title}
            </th>
            {children ??
                (columns > 1 && (
                    <td
                        colSpan={columns - 1}
                        className={cn('border-b', isGroup && 'bg-muted')}
                    />
                ))}
        </tr>
    );
}
