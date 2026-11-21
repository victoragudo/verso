import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { SearchableSelect } from '@/components/verso/ui/searchable-select';
import type { SearchableOption } from '@/lib/option-search';
import { cn } from '@/lib/utils';

export type SelectOption = SearchableOption & { disabled?: boolean };

export type OptionSelectProps = {
    id?: string;
    value: string;
    onValueChange: (value: string) => void;
    options: SelectOption[];
    // Sends the value with a normal form post, in a hidden input.
    name?: string;
    placeholder?: string;
    searchPlaceholder?: string;
    disabled?: boolean;
    // "sm" is the small height of the narrow editor panels.
    size?: 'sm' | 'default';
    // Forces the list with a search box on or off. By default long lists get it.
    searchable?: boolean;
    className?: string;
    'aria-label'?: string;
    'aria-describedby'?: string;
    'aria-invalid'?: boolean;
};

// Lists longer than this get a search box.
export const SEARCH_THRESHOLD = 10;

// Radix does not allow an empty item value, so the empty choice uses this one inside.
const EMPTY_VALUE = '__verso-empty__';

const SMALL_SEARCH_TRIGGER = 'md:h-8';

type OptionGroup = { name: string; options: SelectOption[] };

// Puts options with the same group together, keeping their first order.
function groupOptions(options: SelectOption[]): OptionGroup[] {
    const groups = new Map<string, SelectOption[]>();

    for (const option of options) {
        const name = option.group ?? '';
        groups.set(name, [...(groups.get(name) ?? []), option]);
    }

    return Array.from(groups, ([name, list]) => ({ name, options: list }));
}

const toInner = (value: string): string => (value === '' ? EMPTY_VALUE : value);

const toOuter = (value: string): string => (value === EMPTY_VALUE ? '' : value);

// The admin select from a list of options. Short lists use the normal select,
// long lists the select with a search box. Use it instead of a native select.
export function OptionSelect({
    id,
    value,
    onValueChange,
    options,
    name,
    placeholder,
    searchPlaceholder,
    disabled = false,
    size = 'default',
    searchable,
    className,
    'aria-label': ariaLabel,
    'aria-describedby': ariaDescribedBy,
    'aria-invalid': ariaInvalid,
}: OptionSelectProps) {
    const hasDisabledOption = options.some((option) => option.disabled);
    const withSearch =
        searchable ?? (options.length > SEARCH_THRESHOLD && !hasDisabledOption);
    const hidden = name !== undefined && (
        <input type="hidden" name={name} value={value} />
    );

    if (withSearch) {
        return (
            <>
                <SearchableSelect
                    id={id}
                    value={value}
                    onValueChange={onValueChange}
                    options={options}
                    placeholder={placeholder}
                    searchPlaceholder={searchPlaceholder}
                    disabled={disabled}
                    aria-label={ariaLabel}
                    aria-describedby={ariaDescribedBy}
                    aria-invalid={ariaInvalid}
                    className={className}
                    triggerClassName={
                        size === 'sm' ? SMALL_SEARCH_TRIGGER : undefined
                    }
                    showSelectedDescription={false}
                />
                {hidden}
            </>
        );
    }

    const known = options.some((option) => option.value === value);

    return (
        <>
            <Select
                value={known ? toInner(value) : ''}
                onValueChange={(next) => onValueChange(toOuter(next))}
                disabled={disabled}
            >
                <SelectTrigger
                    id={id}
                    size={size}
                    aria-label={ariaLabel}
                    aria-describedby={ariaDescribedBy}
                    aria-invalid={ariaInvalid}
                    className={cn('w-full min-w-0', className)}
                >
                    <SelectValue placeholder={placeholder} />
                </SelectTrigger>
                <SelectContent className="max-w-(--radix-select-content-available-width)">
                    {groupOptions(options).map((group) =>
                        group.name === '' ? (
                            group.options.map(renderItem)
                        ) : (
                            <SelectGroup key={group.name}>
                                <SelectLabel>{group.name}</SelectLabel>
                                {group.options.map(renderItem)}
                            </SelectGroup>
                        ),
                    )}
                </SelectContent>
            </Select>
            {hidden}
        </>
    );
}

function renderItem(option: SelectOption) {
    return (
        <SelectItem
            key={option.value}
            value={toInner(option.value)}
            disabled={option.disabled}
            lang={option.lang}
        >
            {option.label}
        </SelectItem>
    );
}
