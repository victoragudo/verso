import { ArrowUp } from 'lucide-react';
import type { Key, ReactNode } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { DEFAULT_CELL_CLASSES } from './list-view';
import type { ColumnDefault, Density } from './list-view';
import { RowActions } from './row-actions';
import type { RowAction } from './row-actions';

export type DataTableColumn<T> = {
    key: string;
    header: ReactNode;
    cell: (row: T) => ReactNode;
    className?: string;
    primary?: boolean;
    hideOnMobile?: boolean;
    wrap?: boolean;
    // An optional column can be shown or hidden with the column chooser.
    optional?: boolean;
    // When the user did not pick columns, the list width from which the column shows.
    defaultFrom?: ColumnDefault;
    // Name in the column chooser, when the header is not plain text.
    label?: string;
    // The header becomes a button that sorts the list by this column.
    sortable?: boolean;
};

export type SortDirection = 'ascending' | 'descending';

// The column the list is sorted by and what to do when a sortable header is pressed.
export type DataTableSort = {
    column: string;
    direction: SortDirection;
    onSort: (column: string) => void;
};

type Selection<T, K extends Key> = {
    selected: K[];
    onChange: (selected: K[]) => void;
    label: (row: T) => string;
};

type Props<T, K extends Key> = {
    rows: T[];
    columns: DataTableColumn<T>[];
    rowKey: (row: T) => K;
    rowActions?: (row: T) => RowAction[];
    rowLabel?: (row: T) => string;
    selection?: Selection<T, K>;
    empty?: ReactNode;
    caption?: string;
    className?: string;
    // Optional columns the user picked, or null for the defaults of the list width.
    visibleColumns?: string[] | null;
    density?: Density;
    sort?: DataTableSort;
};

// A server sort key with the column it orders and its direction.
export type SortOption = { column: string; direction: SortDirection };

// Builds the table sort from the sort keys a list page knows. Each column has one
// direction, so pressing the header of the current column keeps the order.
export function sortByKey<S extends string>(
    options: Record<S, SortOption>,
    current: S,
    onChange: (sort: S) => void,
): DataTableSort {
    const keys = Object.keys(options) as S[];

    return {
        column: options[current].column,
        direction: options[current].direction,
        onSort: (column) => {
            const next = keys.find((key) => options[key].column === column);

            if (next !== undefined && next !== current) {
                onChange(next);
            }
        },
    };
}

// Row padding for each density.
const CELL_PADDING: Record<Density, string> = {
    comfortable: 'py-2.5',
    compact: 'py-1',
};

// Keeps the columns the user picked. Without a choice every column stays and
// optional ones get container query classes, so wide lists show more of them.
export function shownColumns<T>(
    columns: DataTableColumn<T>[],
    visibleColumns: string[] | null | undefined,
): { column: DataTableColumn<T>; widthClass: string }[] {
    if (visibleColumns) {
        return columns
            .filter(
                (column) =>
                    !column.optional || visibleColumns.includes(column.key),
            )
            .map((column) => ({ column, widthClass: '' }));
    }

    return columns
        .filter((column) => column.defaultFrom !== 'never')
        .map((column) => ({
            column,
            widthClass: column.optional
                ? DEFAULT_CELL_CLASSES[column.defaultFrom ?? 'always']
                : '',
        }));
}

// Dense table with a sticky header and row menus. Becomes cards when the list
// is narrower than 40rem. The wrapper is a size container, so optional columns
// appear as the list gets wider.
export function DataTable<T, K extends Key>({
    rows,
    columns,
    rowKey,
    rowActions,
    rowLabel,
    selection,
    empty,
    caption,
    className,
    visibleColumns,
    density = 'comfortable',
    sort,
}: Props<T, K>) {
    const shown = shownColumns(columns, visibleColumns);
    const cellPadding = CELL_PADDING[density];
    const keys = rows.map(rowKey);
    const allSelected =
        selection !== undefined &&
        rows.length > 0 &&
        keys.every((key) => selection.selected.includes(key));

    const toggle = (key: K, checked: boolean) => {
        if (!selection) {
            return;
        }

        selection.onChange(
            checked
                ? [...selection.selected, key]
                : selection.selected.filter((value) => value !== key),
        );
    };

    if (rows.length === 0 && empty) {
        return (
            <div
                className={cn('rounded-lg border bg-card shadow-xs', className)}
            >
                {empty}
            </div>
        );
    }

    const actionsLabel = (row: T) =>
        rowLabel ? t('Actions for {name}', { name: rowLabel(row) }) : undefined;

    const headerContent = (column: DataTableColumn<T>) =>
        column.sortable && sort ? (
            <button
                type="button"
                onClick={() => sort.onSort(column.key)}
                className="group/sort -mx-2 inline-flex min-h-6 items-center gap-1 rounded-sm px-2 py-1 font-medium text-inherit outline-none hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring"
            >
                {column.header}
                <ArrowUp
                    aria-hidden="true"
                    className="size-3.5 shrink-0 opacity-0 transition-[opacity,rotate] motion-reduce:transition-none group-hover/sort:opacity-60 group-focus-visible/sort:opacity-60 in-aria-[sort]:opacity-100 in-aria-[sort=descending]:rotate-180"
                />
            </button>
        ) : (
            column.header
        );

    const primary = columns.find((column) => column.primary) ?? columns[0];
    // Cards on small screens show optional columns only when the user picked them.
    const secondary = shown
        .map(({ column }) => column)
        .filter(
            (column) =>
                column !== primary &&
                !column.hideOnMobile &&
                (!column.optional ||
                    visibleColumns?.includes(column.key) ||
                    column.defaultFrom === 'always'),
        );

    return (
        <div
            data-density={density}
            className={cn(
                '@container/table rounded-lg border bg-card shadow-xs',
                className,
            )}
        >
            <table
                className={cn(
                    'hidden w-full tabular-nums @min-[40rem]/table:table',
                    density === 'compact' ? 'text-[0.8125rem]' : 'text-sm',
                )}
            >
                {caption && <caption className="sr-only">{caption}</caption>}
                <thead>
                    <tr>
                        {selection && (
                            <th
                                scope="col"
                                className="sticky top-14 z-10 w-10 rounded-ss-lg border-b bg-muted/95 py-2 ps-4 text-start backdrop-blur"
                            >
                                <Checkbox
                                    aria-label={t('Select all rows')}
                                    checked={allSelected}
                                    onCheckedChange={(checked) =>
                                        selection.onChange(
                                            checked === true ? keys : [],
                                        )
                                    }
                                />
                            </th>
                        )}
                        {shown.map(({ column, widthClass }, index) => (
                            <th
                                key={column.key}
                                scope="col"
                                aria-sort={
                                    column.sortable &&
                                    sort?.column === column.key
                                        ? sort.direction
                                        : undefined
                                }
                                className={cn(
                                    'sticky top-14 z-10 border-b bg-muted/95 px-4 py-2 text-start text-xs font-medium whitespace-nowrap text-muted-foreground backdrop-blur',
                                    index === 0 &&
                                        !selection &&
                                        'rounded-ss-lg',
                                    index === shown.length - 1 &&
                                        !rowActions &&
                                        'rounded-se-lg',
                                    column.className,
                                    widthClass,
                                )}
                            >
                                {headerContent(column)}
                            </th>
                        ))}
                        {rowActions && (
                            <th
                                scope="col"
                                className="sticky top-14 z-10 w-12 rounded-se-lg border-b bg-muted/95 px-2 py-2 backdrop-blur"
                            >
                                <span className="sr-only">{t('Actions')}</span>
                            </th>
                        )}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row, rowIndex) => {
                        const key = keys[rowIndex];
                        const isSelected =
                            selection?.selected.includes(key) ?? false;

                        return (
                            <tr
                                key={key}
                                data-state={isSelected ? 'selected' : undefined}
                                className="border-b transition-colors last:border-b-0 hover:bg-muted/50 data-[state=selected]:bg-brand-soft/60"
                            >
                                {selection && (
                                    <td
                                        className={cn(
                                            'ps-4 align-middle',
                                            cellPadding,
                                        )}
                                    >
                                        <Checkbox
                                            aria-label={selection.label(row)}
                                            checked={isSelected}
                                            onCheckedChange={(checked) =>
                                                toggle(key, checked === true)
                                            }
                                        />
                                    </td>
                                )}
                                {shown.map(({ column, widthClass }) => (
                                    <td
                                        key={column.key}
                                        className={cn(
                                            'px-4 align-middle',
                                            cellPadding,
                                            column.className,
                                            widthClass,
                                        )}
                                    >
                                        {column.cell(row)}
                                    </td>
                                ))}
                                {rowActions && (
                                    <td
                                        className={cn(
                                            'px-2 text-end align-middle',
                                            density === 'compact'
                                                ? 'py-0.5'
                                                : 'py-1.5',
                                        )}
                                    >
                                        <RowActions
                                            actions={rowActions(row)}
                                            label={actionsLabel(row)}
                                        />
                                    </td>
                                )}
                            </tr>
                        );
                    })}
                </tbody>
            </table>

            <ul className="divide-y @min-[40rem]/table:hidden">
                {rows.map((row, rowIndex) => {
                    const key = keys[rowIndex];
                    const isSelected =
                        selection?.selected.includes(key) ?? false;

                    return (
                        <li
                            key={key}
                            className={cn(
                                'flex gap-3 p-4',
                                isSelected && 'bg-brand-soft/60',
                            )}
                        >
                            {selection && (
                                <label className="-m-2.5 flex size-10 shrink-0 items-center justify-center">
                                    <Checkbox
                                        aria-label={selection.label(row)}
                                        checked={isSelected}
                                        onCheckedChange={(checked) =>
                                            toggle(key, checked === true)
                                        }
                                    />
                                </label>
                            )}
                            <div className="min-w-0 flex-1">
                                <div className="min-w-0 text-sm font-medium text-foreground">
                                    {primary.cell(row)}
                                </div>
                                {secondary.length > 0 && (
                                    <dl className="mt-2 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                                        {secondary.map((column) => (
                                            <div
                                                key={column.key}
                                                className="contents"
                                            >
                                                <dt className="text-muted-foreground">
                                                    {column.header}
                                                </dt>
                                                <dd
                                                    className={cn(
                                                        'min-w-0',
                                                        !column.wrap &&
                                                            'truncate',
                                                    )}
                                                >
                                                    {column.cell(row)}
                                                </dd>
                                            </div>
                                        ))}
                                    </dl>
                                )}
                            </div>
                            {rowActions && (
                                <div className="-my-2 -me-2 shrink-0">
                                    <RowActions
                                        actions={rowActions(row)}
                                        label={actionsLabel(row)}
                                    />
                                </div>
                            )}
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}
