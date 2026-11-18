import { Search } from 'lucide-react';
import { useId } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { t } from '@/lib/i18n';

export const ALL_GROUPS = 'all';

type Props = {
    search: string;
    onSearch: (value: string) => void;
    groups?: string[];
    group?: string;
    onGroup?: (value: string) => void;
    onlyDifferences?: boolean;
    onOnlyDifferences?: (value: boolean) => void;
};

// Search box, group picker and "only differences" switch above a capability list.
export function CapabilityFilters({
    search,
    onSearch,
    groups,
    group = ALL_GROUPS,
    onGroup,
    onlyDifferences,
    onOnlyDifferences,
}: Props) {
    const id = useId();

    return (
        <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
            <div
                role="search"
                className="relative min-w-0 flex-1 basis-60 @3xl/page:max-w-sm"
            >
                <Search
                    aria-hidden="true"
                    className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    type="search"
                    value={search}
                    onChange={(event) => onSearch(event.target.value)}
                    placeholder={t('Search capabilities')}
                    aria-label={t('Search capabilities')}
                    className="ps-9"
                />
            </div>
            {groups && onGroup && (
                <div className="w-full basis-48 @lg/page:w-56 @lg/page:flex-none">
                    <Select value={group} onValueChange={onGroup}>
                        <SelectTrigger aria-label={t('Capability group')}>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL_GROUPS}>
                                {t('All groups')}
                            </SelectItem>
                            {groups.map((item) => (
                                <SelectItem key={item} value={item}>
                                    {item}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            )}
            {onOnlyDifferences && (
                <div className="flex min-h-10 items-center gap-2">
                    <Checkbox
                        id={`${id}-differences`}
                        checked={onlyDifferences}
                        onCheckedChange={(value) =>
                            onOnlyDifferences(value === true)
                        }
                    />
                    <Label
                        htmlFor={`${id}-differences`}
                        className="font-normal"
                    >
                        {t('Show only differences')}
                    </Label>
                </div>
            )}
        </div>
    );
}
