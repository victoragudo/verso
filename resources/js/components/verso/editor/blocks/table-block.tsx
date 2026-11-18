import { Minus, Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { BlockFieldProps } from './shared';
import { boolAttr, stringAttr, withAttrs } from './shared';

// Reads the rows attr as a clean grid of strings.
export function tableRows(value: unknown): string[][] {
    if (!Array.isArray(value)) {
        return [];
    }

    return value
        .filter((row): row is unknown[] => Array.isArray(row))
        .map((row) =>
            row.map((cell) => (typeof cell === 'string' ? cell : '')),
        );
}

// A grid of cells with buttons to add and remove rows and columns.
export function TableBlockFields({ block, onChange }: BlockFieldProps) {
    const rows = tableRows(block.attrs.rows);
    const columnCount = Math.max(1, ...rows.map((row) => row.length));
    const header = boolAttr(block, 'header');
    const headerId = `${block.id}-header`;

    const setRows = (next: string[][]) =>
        onChange(withAttrs(block, { rows: next }));

    const setCell = (rowIndex: number, cellIndex: number, value: string) =>
        setRows(
            rows.map((row, r) =>
                r === rowIndex
                    ? Array.from({ length: columnCount }, (_, c) =>
                          c === cellIndex ? value : (row[c] ?? ''),
                      )
                    : row,
            ),
        );

    return (
        <div className="grid gap-2">
            <div className="overflow-x-auto">
                <table className="w-full border-collapse text-sm">
                    <tbody>
                        {rows.map((row, rowIndex) => (
                            <tr key={rowIndex}>
                                {Array.from(
                                    { length: columnCount },
                                    (_, cellIndex) => (
                                        <td
                                            key={cellIndex}
                                            className="border border-zinc-200 p-0 dark:border-zinc-800"
                                        >
                                            <Input
                                                aria-label={t(
                                                    'Row {row}, column {column}',
                                                    {
                                                        row: rowIndex + 1,
                                                        column: cellIndex + 1,
                                                    },
                                                )}
                                                value={row[cellIndex] ?? ''}
                                                onChange={(event) =>
                                                    setCell(
                                                        rowIndex,
                                                        cellIndex,
                                                        event.target.value,
                                                    )
                                                }
                                                className={cn(
                                                    'h-9 min-w-28 rounded-none border-0 shadow-none focus-visible:ring-1',
                                                    header &&
                                                        rowIndex === 0 &&
                                                        'bg-zinc-50 font-semibold dark:bg-zinc-900',
                                                )}
                                            />
                                        </td>
                                    ),
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <div className="flex flex-wrap items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() =>
                        setRows([...rows, Array(columnCount).fill('')])
                    }
                >
                    <Plus /> {t('Row')}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={rows.length <= 1}
                    onClick={() => setRows(rows.slice(0, -1))}
                >
                    <Minus /> {t('Row')}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => setRows(rows.map((row) => [...row, '']))}
                >
                    <Plus /> {t('Column')}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={columnCount <= 1}
                    onClick={() =>
                        setRows(
                            rows.map((row) => row.slice(0, columnCount - 1)),
                        )
                    }
                >
                    <Minus /> {t('Column')}
                </Button>
                <div className="flex items-center gap-2">
                    <Checkbox
                        id={headerId}
                        checked={header}
                        onCheckedChange={(checked) =>
                            onChange(
                                withAttrs(block, { header: checked === true }),
                            )
                        }
                    />
                    <Label htmlFor={headerId}>
                        {t('First row is a header')}
                    </Label>
                </div>
            </div>
            <Input
                aria-label={t('Table caption')}
                placeholder={t('Caption')}
                value={stringAttr(block, 'caption')}
                onChange={(event) =>
                    onChange(withAttrs(block, { caption: event.target.value }))
                }
            />
        </div>
    );
}
