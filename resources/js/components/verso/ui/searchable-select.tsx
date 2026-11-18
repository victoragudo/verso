import { CheckIcon, ChevronDownIcon } from 'lucide-react';
import { useEffect, useId, useMemo, useRef, useState } from 'react';
import type { CSSProperties, KeyboardEvent } from 'react';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { SearchIcon } from '@/components/verso/icons';
import { createOptionFilter } from '@/lib/option-search';
import type { SearchableOption } from '@/lib/option-search';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export type { SearchableOption } from '@/lib/option-search';

export type SearchableSelectProps = {
    id?: string;
    value: string;
    onValueChange: (value: string) => void;
    options: SearchableOption[];
    placeholder?: string;
    searchPlaceholder?: string;
    emptyText?: string;
    disabled?: boolean;
    'aria-invalid'?: boolean;
    'aria-label'?: string;
    'aria-describedby'?: string;
    className?: string;
    // Extra classes of the closed select button, like a smaller height.
    triggerClassName?: string;
    // Shows the description of the picked option in the closed select. Off for short labels like units.
    showSelectedDescription?: boolean;
    // Called with the search text, so a caller can load options from the server.
    onSearchChange?: (query: string) => void;
};

type OptionGroup = { name: string; options: SearchableOption[] };

const MAX_VISIBLE_OPTIONS = 200;
const MIN_PANEL_WIDTH = 256;
// Page Up and Page Down move the active option by this many rows.
const PAGE_ROWS = 10;

// Puts options with the same group together, keeping their first order.
function groupOptions(options: SearchableOption[]): OptionGroup[] {
    const groups = new Map<string, SearchableOption[]>();

    for (const option of options) {
        const name = option.group ?? '';
        const list = groups.get(name);

        if (list) {
            list.push(option);
        } else {
            groups.set(name, [option]);
        }
    }

    return Array.from(groups, ([name, list]) => ({ name, options: list }));
}

// Where the shown part of a long list starts, so the selected option is inside it.
function windowStart(options: SearchableOption[], value: string): number {
    const selectedIndex = options.findIndex((option) => option.value === value);

    if (selectedIndex < MAX_VISIBLE_OPTIONS) {
        return 0;
    }

    return Math.min(
        selectedIndex - Math.floor(MAX_VISIBLE_OPTIONS / 2),
        options.length - MAX_VISIBLE_OPTIONS,
    );
}

// True for a key that types a visible character.
function isTypingKey(event: KeyboardEvent): boolean {
    return (
        event.key.length === 1 &&
        event.key !== ' ' &&
        !event.ctrlKey &&
        !event.metaKey &&
        !event.altKey
    );
}

// A select with a search box, for long lists. Looks like the normal select when closed.
export function SearchableSelect({
    id,
    value,
    onValueChange,
    options,
    placeholder = t('Select an option'),
    searchPlaceholder = t('Search'),
    emptyText = t('No results found.'),
    disabled = false,
    'aria-invalid': ariaInvalid,
    'aria-label': ariaLabel,
    'aria-describedby': ariaDescribedBy,
    className,
    triggerClassName,
    showSelectedDescription = true,
    onSearchChange,
}: SearchableSelectProps) {
    const baseId = useId();
    const listboxId = `${baseId}-listbox`;
    const wrapperRef = useRef<HTMLDivElement | null>(null);
    const inputRef = useRef<HTMLInputElement | null>(null);
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [activeValue, setActiveValue] = useState<string | null>(null);
    const [panelWidth, setPanelWidth] = useState(MIN_PANEL_WIDTH);

    const filterOptions = useMemo(() => createOptionFilter(options), [options]);
    const selected = useMemo(
        () => options.find((option) => option.value === value),
        [options, value],
    );

    const { groups, visible, hiddenCount } = useMemo(() => {
        const ordered = groupOptions(filterOptions(query)).flatMap(
            (group) => group.options,
        );
        const start = query.trim() === '' ? windowStart(ordered, value) : 0;
        const shown = ordered.slice(start, start + MAX_VISIBLE_OPTIONS);

        return {
            groups: groupOptions(shown),
            visible: shown,
            hiddenCount: ordered.length - shown.length,
        };
    }, [filterOptions, query, value]);

    const foundIndex = visible.findIndex(
        (option) => option.value === activeValue,
    );
    const activeIndex = foundIndex === -1 ? 0 : foundIndex;
    const optionId = (index: number) => `${baseId}-option-${index}`;

    // Opens the panel, with an optional first search text.
    const openWith = (text: string) => {
        setPanelWidth(
            Math.max(wrapperRef.current?.offsetWidth ?? 0, MIN_PANEL_WIDTH),
        );
        setQuery(text);
        onSearchChange?.(text);
        setActiveValue(text ? null : value);
        setOpen(true);
    };

    const onOpenChange = (next: boolean) => {
        if (next) {
            openWith('');
        } else {
            setOpen(false);
        }
    };

    const choose = (option: SearchableOption) => {
        onValueChange(option.value);
        setOpen(false);
    };

    useEffect(() => {
        if (!open) {
            return;
        }

        const input = inputRef.current;

        input?.setSelectionRange(input.value.length, input.value.length);
    }, [open]);

    useEffect(() => {
        if (!open) {
            return;
        }

        document
            .getElementById(`${baseId}-option-${activeIndex}`)
            ?.scrollIntoView({ block: 'nearest' });
    }, [open, activeIndex, baseId]);

    // Opens the closed select with arrows or by typing.
    const onTriggerKeyDown = (event: KeyboardEvent<HTMLButtonElement>) => {
        if (open) {
            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            openWith('');
        } else if (isTypingKey(event)) {
            event.preventDefault();
            openWith(event.key);
        }
    };

    // Moves the active option, picks it, or lets Escape close the panel.
    // Home and End move the text cursor once there is search text.
    const onInputKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        const last = visible.length - 1;
        const moves: Record<string, number> = {
            ArrowDown: Math.min(activeIndex + 1, last),
            ArrowUp: Math.max(activeIndex - 1, 0),
            PageDown: Math.min(activeIndex + PAGE_ROWS, last),
            PageUp: Math.max(activeIndex - PAGE_ROWS, 0),
            ...(query === '' ? { Home: 0, End: last } : {}),
        };

        if (event.key in moves) {
            event.preventDefault();

            if (visible.length > 0) {
                setActiveValue(visible[moves[event.key]].value);
            }

            return;
        }

        if (event.key === 'Enter') {
            event.preventDefault();

            if (visible[activeIndex]) {
                choose(visible[activeIndex]);
            }
        }
    };

    let optionIndex = -1;

    return (
        <div
            ref={wrapperRef}
            className={cn('w-full', className)}
            data-slot="searchable-select"
        >
            <Popover open={open} onOpenChange={onOpenChange}>
                <PopoverTrigger
                    id={id}
                    role="combobox"
                    disabled={disabled}
                    aria-invalid={ariaInvalid}
                    aria-label={ariaLabel}
                    aria-describedby={ariaDescribedBy}
                    data-placeholder={selected ? undefined : ''}
                    onKeyDown={onTriggerKeyDown}
                    className={cn(
                        'flex h-10 w-full items-center justify-between gap-2 rounded-md border border-input bg-card px-3 py-2 text-sm whitespace-nowrap shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 data-[placeholder]:text-muted-foreground md:h-9 dark:bg-input/30 dark:hover:bg-input/50 dark:aria-invalid:ring-destructive/40',
                        triggerClassName,
                    )}
                >
                    <span className="flex min-w-0 items-center gap-2">
                        <span className="truncate" lang={selected?.lang}>
                            {selected?.label ?? placeholder}
                        </span>
                        {showSelectedDescription && selected?.description && (
                            <span className="truncate text-xs text-muted-foreground tabular-nums">
                                {selected.description}
                            </span>
                        )}
                    </span>
                    <ChevronDownIcon
                        aria-hidden="true"
                        className="pointer-events-none size-4 shrink-0 text-muted-foreground opacity-50"
                    />
                </PopoverTrigger>
                <PopoverContent
                    sheetOnMobile
                    aria-label={searchPlaceholder}
                    style={
                        {
                            '--searchable-select-width': `${panelWidth}px`,
                        } as CSSProperties
                    }
                    className="md:w-(--searchable-select-width) md:max-w-[calc(100vw-1rem)]"
                >
                    <div className="flex items-center gap-2 border-b px-3">
                        <SearchIcon
                            size={16}
                            className="shrink-0 text-muted-foreground"
                        />
                        <input
                            ref={inputRef}
                            data-autofocus
                            type="text"
                            role="combobox"
                            aria-label={searchPlaceholder}
                            aria-expanded="true"
                            aria-controls={listboxId}
                            aria-autocomplete="list"
                            aria-activedescendant={
                                visible.length > 0
                                    ? optionId(activeIndex)
                                    : undefined
                            }
                            autoComplete="off"
                            autoCapitalize="off"
                            enterKeyHint="done"
                            spellCheck={false}
                            placeholder={searchPlaceholder}
                            value={query}
                            onChange={(event) => {
                                setQuery(event.target.value);
                                onSearchChange?.(event.target.value);
                                setActiveValue(null);
                            }}
                            onKeyDown={onInputKeyDown}
                            className="h-11 w-full min-w-0 bg-transparent text-base outline-none placeholder:text-muted-foreground md:h-10 md:text-sm"
                        />
                    </div>
                    <div
                        role="status"
                        className={
                            visible.length === 0
                                ? 'px-2 py-6 text-center text-sm text-muted-foreground'
                                : 'sr-only'
                        }
                    >
                        {visible.length === 0 ? emptyText : ''}
                    </div>
                    <div
                        id={listboxId}
                        role="listbox"
                        aria-label={searchPlaceholder}
                        className="max-h-[60dvh] overflow-y-auto overscroll-contain p-1 empty:hidden md:max-h-72"
                    >
                        {groups.map((group) => {
                            const headerId = `${baseId}-group-${group.name}`;

                            return (
                                <div
                                    key={group.name}
                                    role="group"
                                    aria-labelledby={
                                        group.name ? headerId : undefined
                                    }
                                >
                                    {group.name && (
                                        <div
                                            id={headerId}
                                            role="presentation"
                                            className="px-2 pt-2 pb-1 text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            {group.name}
                                        </div>
                                    )}
                                    {group.options.map((option) => {
                                        optionIndex += 1;
                                        const index = optionIndex;
                                        const isSelected =
                                            option.value === value;

                                        return (
                                            <div
                                                key={option.value}
                                                id={optionId(index)}
                                                role="option"
                                                aria-selected={isSelected}
                                                data-active={
                                                    index === activeIndex
                                                        ? ''
                                                        : undefined
                                                }
                                                onMouseDown={(event) =>
                                                    event.preventDefault()
                                                }
                                                onMouseMove={() => {
                                                    if (index !== activeIndex) {
                                                        setActiveValue(
                                                            option.value,
                                                        );
                                                    }
                                                }}
                                                onClick={() => choose(option)}
                                                className="relative flex min-h-10 w-full cursor-pointer items-center gap-2 rounded-sm py-1.5 ps-2 pe-8 text-sm select-none data-[active]:bg-accent data-[active]:text-accent-foreground md:min-h-8"
                                            >
                                                <span
                                                    lang={option.lang}
                                                    className="min-w-0 flex-1 truncate"
                                                >
                                                    {option.label}
                                                </span>
                                                {option.description && (
                                                    <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                                                        {option.description}
                                                    </span>
                                                )}
                                                {isSelected && (
                                                    <CheckIcon
                                                        aria-hidden="true"
                                                        className="absolute end-2 size-4 text-muted-foreground"
                                                    />
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            );
                        })}
                    </div>
                    {hiddenCount > 0 && (
                        <p className="border-t px-3 py-2 text-xs text-muted-foreground">
                            {t('Keep typing to see more')}
                        </p>
                    )}
                </PopoverContent>
            </Popover>
        </div>
    );
}
