import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { BlockTypeDefinition } from './block-types';

export type SlashMenuProps = {
    items: BlockTypeDefinition[];
    activeIndex: number;
    onSelect: (definition: BlockTypeDefinition) => void;
    onHover: (index: number) => void;
};

// The list of block types shown after typing "/" in an empty paragraph.
export function SlashMenu({
    items,
    activeIndex,
    onSelect,
    onHover,
}: SlashMenuProps) {
    if (items.length === 0) {
        return (
            <div className="absolute start-0 top-full z-30 mt-1 w-72 rounded-lg border border-zinc-200 bg-white p-3 text-sm text-muted-foreground shadow-xs dark:border-zinc-800 dark:bg-zinc-950">
                {t('No matching blocks')}
            </div>
        );
    }

    return (
        <div
            role="listbox"
            aria-label={t('Insert a block')}
            className="absolute start-0 top-full z-30 mt-1 max-h-80 w-72 overflow-y-auto rounded-lg border border-zinc-200 bg-white p-1 shadow-xs dark:border-zinc-800 dark:bg-zinc-950"
        >
            {items.map((item, index) => {
                const Icon = item.icon;

                return (
                    <button
                        key={item.type}
                        type="button"
                        role="option"
                        aria-selected={index === activeIndex}
                        onMouseDown={(event) => event.preventDefault()}
                        onClick={() => onSelect(item)}
                        onMouseEnter={() => onHover(index)}
                        className={cn(
                            'flex min-h-10 w-full items-center gap-3 rounded-md px-2 py-1.5 text-start',
                            index === activeIndex
                                ? 'bg-zinc-100 dark:bg-zinc-800'
                                : 'hover:bg-zinc-50 dark:hover:bg-zinc-900',
                        )}
                    >
                        <span className="flex size-8 shrink-0 items-center justify-center rounded-md border border-zinc-200 bg-white text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                            <Icon className="size-4" />
                        </span>
                        <span className="min-w-0">
                            <span className="block text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                {t(item.label)}
                            </span>
                            <span className="block truncate text-xs text-muted-foreground">
                                {t(item.description)}
                            </span>
                        </span>
                    </button>
                );
            })}
        </div>
    );
}

export default SlashMenu;
