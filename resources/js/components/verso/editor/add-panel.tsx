import { Search } from 'lucide-react';
import { useId, useState } from 'react';
import { builder, featureEnabled } from '@/builder';
import type { BuilderBlock, EditorManifest, InsertItem } from '@/builder';
import { DRAG_MIME, dragState } from '@/builder/dnd';
import { Input } from '@/components/ui/input';
import { labelInSentence } from '@/components/verso/editor/blocks/shared';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { Block, BlockPattern } from '@/types';

// Browser storage key for the items people added last.
const RECENT_KEY = 'verso.builder.recent';

// How many recent items to keep when the server sends no number.
const DEFAULT_RECENT_LIMIT = 6;

// Reads the recent list. Storage can be blocked, so errors give an empty list.
export function readRecent(): string[] {
    try {
        const value: unknown = JSON.parse(
            window.localStorage.getItem(RECENT_KEY) ?? '[]',
        );

        return Array.isArray(value)
            ? value.filter((key): key is string => typeof key === 'string')
            : [];
    } catch {
        return [];
    }
}

// Puts an item first in the recent list.
export function rememberRecent(key: string, limit: number): string[] {
    const next = [key, ...readRecent().filter((item) => item !== key)].slice(
        0,
        limit,
    );

    try {
        window.localStorage.setItem(RECENT_KEY, JSON.stringify(next));
    } catch {
        // Storage is full or blocked; the list just is not kept.
    }

    return next;
}

// Items whose label, description or keywords contain every word of the search.
export function filterInsertItems(
    items: InsertItem[],
    query: string,
): InsertItem[] {
    const words = query.toLowerCase().split(/\s+/).filter(Boolean);

    return items.filter((item) => {
        const text = [
            item.label,
            t(item.label),
            item.description,
            t(item.description),
            ...item.keywords,
        ]
            .join(' ')
            .toLowerCase();

        return words.every((word) => text.includes(word));
    });
}

// Groups items by category, in the order of the category registry.
export function groupInsertItems(
    items: InsertItem[],
): { key: string; label: string; items: InsertItem[] }[] {
    const order = builder.categories.all().map((category) => category.name);
    const groups = new Map<string, InsertItem[]>();

    items.forEach((item) =>
        groups.set(item.category, [...(groups.get(item.category) ?? []), item]),
    );

    return [...groups.entries()]
        .sort(([a], [b]) => {
            const left = order.indexOf(a);
            const right = order.indexOf(b);

            return (
                (left === -1 ? order.length : left) -
                (right === -1 ? order.length : right)
            );
        })
        .map(([key, list]) => ({
            key,
            label: builder.categories.get(key)?.label ?? key,
            items: list,
        }));
}

type Props = {
    types: BuilderBlock[];
    patterns: BlockPattern[];
    editor?: EditorManifest;
    onInsert: (blocks: Block[]) => void;
};

// The "Add" panel: every block, pattern and plugin item, to click or drag into the page.
export function AddPanel({ types, patterns, editor, onInsert }: Props) {
    const searchId = useId();
    const sources = builder.insertSources
        .all()
        .filter(
            (source) =>
                !source.feature || featureEnabled(editor, source.feature),
        );
    const [sourceName, setSourceName] = useState(sources[0]?.name ?? '');
    const [query, setQuery] = useState('');
    const [recent, setRecent] = useState<string[]>(readRecent);
    const limit = editor?.recentLimit ?? DEFAULT_RECENT_LIMIT;
    const context = { blocks: types, patterns };
    const source =
        sources.find((item) => item.name === sourceName) ?? sources[0];
    const items = source ? filterInsertItems(source.items(context), query) : [];
    const allItems = sources.flatMap((item) => item.items(context));
    const recentItems = recent
        .map((key) => allItems.find((item) => item.key === key))
        .filter((item): item is InsertItem => item !== undefined);

    const used = (item: InsertItem) =>
        setRecent(rememberRecent(item.key, limit));

    const renderItem = (item: InsertItem) => {
        const Icon = item.icon;

        return (
            <li key={item.key}>
                <button
                    type="button"
                    draggable={!item.load}
                    title={t(item.description)}
                    onClick={() => {
                        if (item.load) {
                            void item
                                .load()
                                .then((blocks) => {
                                    if (blocks.length > 0) {
                                        onInsert(blocks);
                                        used(item);
                                    }
                                })
                                .catch(() => undefined);

                            return;
                        }

                        onInsert(item.create());
                        used(item);
                    }}
                    onDragStart={(event) => {
                        event.dataTransfer.effectAllowed = 'copy';
                        event.dataTransfer.setData(DRAG_MIME, item.key);
                        dragState.start({
                            kind: 'insert',
                            key: item.key,
                            blocks: item.create(),
                        });
                    }}
                    onDragEnd={(event) => {
                        if (event.dataTransfer.dropEffect !== 'none') {
                            used(item);
                        }

                        dragState.end();
                    }}
                    data-insert-item={item.key}
                    className="flex w-full cursor-grab touch-manipulation flex-col items-center gap-1.5 rounded-md border border-transparent p-2 text-center text-xs hover:border-border hover:bg-accent focus-visible:border-ring active:cursor-grabbing"
                >
                    <Icon
                        className="size-5 text-muted-foreground"
                        aria-hidden
                    />
                    <span className="line-clamp-2">{t(item.label)}</span>
                </button>
            </li>
        );
    };

    return (
        <div
            className="flex h-full min-h-0 flex-col gap-3"
            data-test="add-panel"
        >
            {sources.length > 1 && (
                <div
                    role="group"
                    aria-label={t('What to add')}
                    className="flex gap-1 rounded-md bg-muted p-1"
                >
                    {sources.map((item) => (
                        <button
                            key={item.name}
                            type="button"
                            aria-pressed={item.name === source?.name}
                            onClick={() => setSourceName(item.name)}
                            className="flex-1 rounded px-2 py-1 text-sm text-muted-foreground transition-colors hover:text-foreground aria-pressed:bg-background aria-pressed:text-foreground aria-pressed:shadow-xs"
                        >
                            {t(item.label)}
                        </button>
                    ))}
                </div>
            )}
            <div className="relative">
                <label htmlFor={searchId} className="sr-only">
                    {source
                        ? t('Search {items}', {
                              items: labelInSentence(source.label),
                          })
                        : t('Search items')}
                </label>
                <Search
                    className="pointer-events-none absolute start-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden
                />
                <Input
                    id={searchId}
                    type="search"
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder={t('Search')}
                    className="ps-8"
                />
            </div>
            <p className="text-xs text-muted-foreground">
                {t(
                    'Click to add after the selected block, or drag into the page.',
                )}
            </p>
            <div className="-mx-1 min-h-0 flex-1 overflow-y-auto px-1 pb-4">
                {query === '' &&
                    recentItems.length > 0 &&
                    source === sources[0] && (
                        <section
                            aria-labelledby={`${searchId}-recent`}
                            className="mb-4"
                        >
                            <h3
                                id={`${searchId}-recent`}
                                className="mb-1 text-xs font-semibold text-muted-foreground uppercase"
                            >
                                {t('Recently used')}
                            </h3>
                            <ul className="grid grid-cols-3 gap-1">
                                {recentItems.map(renderItem)}
                            </ul>
                        </section>
                    )}
                {groupInsertItems(items).map((group) => (
                    <section
                        key={group.key}
                        aria-labelledby={`${searchId}-${group.key}`}
                        className="mb-4"
                    >
                        <h3
                            id={`${searchId}-${group.key}`}
                            className="mb-1 text-xs font-semibold text-muted-foreground uppercase"
                        >
                            {t(group.label)}
                        </h3>
                        <ul className="grid grid-cols-3 gap-1">
                            {group.items.map(renderItem)}
                        </ul>
                    </section>
                ))}
                {items.length === 0 && (
                    <p
                        className={cn(
                            'py-6 text-center text-sm text-muted-foreground',
                        )}
                    >
                        {t('Nothing found. Try another word.')}
                    </p>
                )}
            </div>
        </div>
    );
}
