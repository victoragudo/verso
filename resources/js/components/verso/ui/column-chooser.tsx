import { Columns3 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { t } from '@/lib/i18n';
import { defaultColumnsAt } from './list-view';
import type { ColumnDefault, Density } from './list-view';

export type ChooserColumn = {
    key: string;
    label: string;
    defaultFrom?: ColumnDefault;
};

type Props = {
    columns: ChooserColumn[];
    visible: string[] | null;
    onChange: (columns: string[] | null) => void;
    density: Density;
    onDensityChange: (density: Density) => void;
    // Width of the list in pixels, to know which columns show by default. Defaults to the page width.
    listWidth?: () => number;
};

// The chooser entries for the optional columns of a table.
export function chooserColumns(
    columns: {
        key: string;
        label?: string;
        optional?: boolean;
        defaultFrom?: ColumnDefault;
    }[],
): ChooserColumn[] {
    return columns
        .filter((column) => column.optional)
        .map((column) => ({
            key: column.key,
            label: column.label ?? column.key,
            defaultFrom: column.defaultFrom,
        }));
}

function pageWidth(): number {
    return document.querySelector('main')?.clientWidth ?? window.innerWidth;
}

// A menu to pick the optional columns of a list and its density. The choice is saved per user.
export function ColumnChooser({
    columns,
    visible,
    onChange,
    density,
    onDensityChange,
    listWidth = pageWidth,
}: Props) {
    // Without a choice, the boxes show the columns the current width shows.
    const current = () => visible ?? defaultColumnsAt(columns, listWidth());

    const toggle = (key: string, checked: boolean) => {
        const base = current().filter((value) => value !== key);

        onChange(checked ? [...base, key] : base);
    };

    const checked = current();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" className="min-h-10 md:min-h-9">
                    <Columns3 aria-hidden="true" />
                    {t('View')}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className="max-h-[min(32rem,70dvh)] w-60 overflow-y-auto"
            >
                <DropdownMenuLabel>{t('Columns')}</DropdownMenuLabel>
                {columns.map((column) => (
                    <DropdownMenuCheckboxItem
                        key={column.key}
                        checked={checked.includes(column.key)}
                        onCheckedChange={(value) =>
                            toggle(column.key, value === true)
                        }
                        onSelect={(event) => event.preventDefault()}
                    >
                        {column.label}
                    </DropdownMenuCheckboxItem>
                ))}
                <DropdownMenuItem
                    disabled={visible === null}
                    onSelect={() => onChange(null)}
                >
                    {t('Use the default columns')}
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuLabel>{t('Density')}</DropdownMenuLabel>
                <DropdownMenuRadioGroup
                    value={density}
                    onValueChange={(value) => onDensityChange(value as Density)}
                >
                    <DropdownMenuRadioItem
                        value="comfortable"
                        onSelect={(event) => event.preventDefault()}
                    >
                        {t('Comfortable')}
                    </DropdownMenuRadioItem>
                    <DropdownMenuRadioItem
                        value="compact"
                        onSelect={(event) => event.preventDefault()}
                    >
                        {t('Compact')}
                    </DropdownMenuRadioItem>
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
