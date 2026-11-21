import { useCallback, useState } from 'react';
import { sendJson } from '@/lib/json-request';
import { update as saveListView } from '@/routes/admin/list-views';

// From which list width an optional column shows when the user did not pick columns.
export type ColumnDefault =
    | 'always'
    | 'md'
    | 'lg'
    | 'xl'
    | '2xl'
    | '3xl'
    | 'never';

export type Density = 'comfortable' | 'compact';

// The saved view of one list. Null columns means "the defaults for the width".
export type ListViewState = {
    key: string;
    columns: string[] | null;
    density: Density | null;
};

// List widths in rem for each default. Wide lists (ultrawide screens) show more columns.
export const DEFAULT_WIDTHS_REM: Record<ColumnDefault, number> = {
    always: 0,
    md: 48,
    lg: 64,
    xl: 80,
    '2xl': 100,
    '3xl': 125,
    never: Number.POSITIVE_INFINITY,
};

// Container query classes that match DEFAULT_WIDTHS_REM. Written out in full so Tailwind finds them.
export const DEFAULT_CELL_CLASSES: Record<ColumnDefault, string> = {
    always: '',
    md: 'hidden @min-[48rem]/table:table-cell',
    lg: 'hidden @min-[64rem]/table:table-cell',
    xl: 'hidden @min-[80rem]/table:table-cell',
    '2xl': 'hidden @min-[100rem]/table:table-cell',
    '3xl': 'hidden @min-[125rem]/table:table-cell',
    never: 'hidden',
};

// The optional columns that show by default at a list width in pixels.
export function defaultColumnsAt(
    columns: { key: string; defaultFrom?: ColumnDefault }[],
    widthPx: number,
    remPx = 16,
): string[] {
    return columns
        .filter(
            (column) =>
                widthPx >=
                DEFAULT_WIDTHS_REM[column.defaultFrom ?? 'always'] * remPx,
        )
        .map((column) => column.key);
}

// The columns and density of a list, saved for the user on the server.
// Changes show at once; a failed save keeps the change for this visit.
export function useListView(initial: ListViewState) {
    const [columns, setColumnsState] = useState<string[] | null>(
        initial.columns,
    );
    const [density, setDensityState] = useState<Density>(
        initial.density ?? 'comfortable',
    );

    const save = useCallback(
        (next: { columns: string[] | null; density: Density }) => {
            sendJson(saveListView.url(initial.key), 'PUT', next).catch(
                () => undefined,
            );
        },
        [initial.key],
    );

    const setColumns = (next: string[] | null) => {
        setColumnsState(next);
        save({ columns: next, density });
    };

    const setDensity = (next: Density) => {
        setDensityState(next);
        save({ columns, density: next });
    };

    return { columns, density, setColumns, setDensity };
}
