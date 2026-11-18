import { useId, useMemo, useState } from 'react';
import type { KeyboardEvent } from 'react';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { PatternsIcon, SearchIcon } from '@/components/verso/icons';
import { t } from '@/lib/i18n';
import type { BlockPattern } from '@/types';
import { groupPatterns, searchPatterns } from './patterns';

type PatternPickerProps = {
    patterns: BlockPattern[];
    onInsert: (pattern: BlockPattern) => void;
};

// The "Patterns" button of the editor toolbar: a searchable list of theme patterns grouped by category.
export function PatternPicker({ patterns, onInsert }: PatternPickerProps) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const listId = useId();
    const visible = useMemo(
        () => searchPatterns(patterns, query),
        [patterns, query],
    );
    const groups = useMemo(() => groupPatterns(visible), [visible]);

    const onOpenChange = (next: boolean) => {
        setOpen(next);

        if (!next) {
            setQuery('');
        }
    };

    const choose = (pattern: BlockPattern) => {
        onInsert(pattern);
        onOpenChange(false);
    };

    // Enter in the search box inserts the first pattern that matches.
    const onSearchKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Enter') {
            event.preventDefault();

            // Enter that confirms an IME composition (Japanese, Chinese, Korean) must not insert.
            if (event.nativeEvent.isComposing || event.keyCode === 229) {
                return;
            }

            if (visible[0]) {
                choose(visible[0]);
            }
        }
    };

    return (
        <Popover open={open} onOpenChange={onOpenChange}>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    data-test="insert-pattern"
                    className="h-8 shrink-0 gap-1.5 px-2 text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100 pointer-coarse:h-10"
                    onMouseDown={(event) => event.preventDefault()}
                >
                    <PatternsIcon size={18} />
                    {t('Patterns')}
                </Button>
            </PopoverTrigger>
            <PopoverContent
                sheetOnMobile
                align="end"
                aria-label={t('Insert a pattern')}
                className="md:w-80"
            >
                <div className="flex items-center gap-2 border-b px-3">
                    <SearchIcon
                        size={16}
                        className="shrink-0 text-muted-foreground"
                    />
                    <input
                        data-autofocus
                        type="search"
                        aria-label={t('Search patterns')}
                        aria-controls={listId}
                        autoComplete="off"
                        spellCheck={false}
                        placeholder={t('Search patterns')}
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        onKeyDown={onSearchKeyDown}
                        className="h-11 w-full min-w-0 bg-transparent text-base outline-none placeholder:text-muted-foreground md:h-10 md:text-sm"
                    />
                </div>
                <div
                    id={listId}
                    className="max-h-[60dvh] overflow-y-auto overscroll-contain p-1 md:max-h-80"
                >
                    {visible.length === 0 && (
                        <p className="px-2 py-6 text-center text-sm text-muted-foreground">
                            {t('No pattern matches.')}
                        </p>
                    )}
                    {groups.map((group) => (
                        <section
                            key={group.category}
                            aria-label={group.category}
                        >
                            <h3 className="px-2 pt-2 pb-1 text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                                {group.category}
                            </h3>
                            <ul>
                                {group.patterns.map((pattern) => (
                                    <li key={pattern.name}>
                                        <button
                                            type="button"
                                            data-test={`pattern-${pattern.name}`}
                                            onClick={() => choose(pattern)}
                                            className="flex min-h-10 w-full flex-col items-start gap-0.5 rounded-sm px-2 py-1.5 text-start text-sm outline-none hover:bg-accent focus-visible:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        >
                                            <span className="font-medium text-foreground">
                                                {pattern.title}
                                            </span>
                                            {pattern.description !== '' && (
                                                <span className="text-xs text-muted-foreground">
                                                    {pattern.description}
                                                </span>
                                            )}
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    ))}
                </div>
            </PopoverContent>
        </Popover>
    );
}

export default PatternPicker;
