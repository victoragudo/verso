import { Head, Link, useForm } from '@inertiajs/react';
import { Download, Pencil, Repeat, Upload } from 'lucide-react';
import { useId, useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    EmptyState,
    PageHeader,
    SiteDate,
    StatusBadge,
} from '@/components/verso/ui';
import { msg, t } from '@/lib/i18n';
import { matchesSearch, normalizeSearchText } from '@/lib/option-search';
import { importMethod } from '@/routes/admin/library';

type Item = {
    id: number;
    kind: 'synced' | 'pattern';
    title: string;
    category: string;
    keywords: string[];
    blocks: number;
    updatedAt: string | null;
    editUrl: string;
};

type ThemePattern = {
    name: string;
    title: string;
    category: string;
    description: string;
    keywords: string[];
};

type Props = { items: Item[]; patterns: ThemePattern[]; exportUrl: string };

type Filter = 'all' | 'synced' | 'pattern' | 'theme';

const FILTERS: [Filter, string][] = [
    ['all', msg('Everything')],
    ['synced', msg('Synced blocks')],
    ['pattern', msg('My patterns')],
    ['theme', msg('From the theme and plugins')],
];

// The design library: synced blocks, saved patterns and the patterns of the theme and plugins in one searchable list.
export default function LibraryIndex({ items, patterns, exportUrl }: Props) {
    const id = useId();
    const [filter, setFilter] = useState<Filter>('all');
    const [query, setQuery] = useState('');
    const upload = useForm<{ file: File | null }>({ file: null });
    const needle = normalizeSearchText(query);
    const matches = (words: string[]) =>
        needle === '' ||
        matchesSearch(normalizeSearchText(words.join(' ')), needle);

    const shownItems = useMemo(
        () =>
            items.filter(
                (item) =>
                    (filter === 'all' || filter === item.kind) &&
                    matches([item.title, item.category, ...item.keywords]),
            ),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [items, filter, needle],
    );
    const shownPatterns = useMemo(
        () =>
            filter === 'all' || filter === 'theme'
                ? patterns.filter((pattern) =>
                      matches([
                          pattern.title,
                          pattern.category,
                          pattern.description,
                          ...pattern.keywords,
                      ]),
                  )
                : [],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [patterns, filter, needle],
    );

    return (
        <>
            <Head title={t('Library')} />
            <PageHeader
                title={t('Library')}
                icon="patterns"
                description={t(
                    'Synced blocks and patterns saved from the editor, and the patterns of your theme and plugins. Save new ones from the menu of any block.',
                )}
                actions={
                    <Button asChild variant="outline">
                        <a href={exportUrl}>
                            <Download />
                            {t('Export everything')}
                        </a>
                    </Button>
                }
            />
            <div className="mb-6 flex flex-wrap items-end gap-4">
                <div className="grid gap-1">
                    <Label htmlFor={`${id}-search`}>
                        {t('Search the library')}
                    </Label>
                    <Input
                        id={`${id}-search`}
                        type="search"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        className="w-72"
                    />
                </div>
                <div
                    role="group"
                    aria-label={t('Show')}
                    className="flex flex-wrap gap-1"
                >
                    {FILTERS.map(([value, label]) => (
                        <Button
                            key={value}
                            type="button"
                            size="sm"
                            variant={filter === value ? 'default' : 'outline'}
                            aria-pressed={filter === value}
                            onClick={() => setFilter(value)}
                        >
                            {t(label)}
                        </Button>
                    ))}
                </div>
                <form
                    className="ms-auto flex flex-wrap items-end gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        upload.post(importMethod().url, {
                            forceFormData: true,
                        });
                    }}
                >
                    <div className="grid gap-1">
                        <Label htmlFor={`${id}-file`}>
                            {t('Import a library file (JSON)')}
                        </Label>
                        <Input
                            id={`${id}-file`}
                            type="file"
                            accept="application/json,.json"
                            onChange={(event) =>
                                upload.setData(
                                    'file',
                                    event.target.files?.[0] ?? null,
                                )
                            }
                            className="w-72"
                        />
                    </div>
                    <Button
                        type="submit"
                        variant="outline"
                        disabled={
                            upload.data.file === null || upload.processing
                        }
                    >
                        <Upload />
                        {t('Import')}
                    </Button>
                    {upload.errors.file && (
                        <p
                            role="alert"
                            className="basis-full text-sm text-destructive"
                        >
                            {upload.errors.file}
                        </p>
                    )}
                </form>
            </div>
            {shownItems.length === 0 && shownPatterns.length === 0 ? (
                <EmptyState
                    icon="patterns"
                    title={t('Nothing found')}
                    description={t(
                        'Save blocks from the editor with "Save to the library" in the menu of a block.',
                    )}
                />
            ) : (
                <ul
                    className="auto-grid gap-4 [--grid-min:18rem]"
                    data-test="library-items"
                >
                    {shownItems.map((item) => (
                        <li
                            key={`item-${item.id}`}
                            className="flex flex-col gap-2 rounded-lg border bg-card p-5"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <h2 className="text-base font-semibold">
                                    {item.title}
                                </h2>
                                <StatusBadge
                                    tone={
                                        item.kind === 'synced'
                                            ? 'info'
                                            : 'neutral'
                                    }
                                >
                                    {item.kind === 'synced'
                                        ? t('Synced block')
                                        : t('Pattern')}
                                </StatusBadge>
                            </div>
                            <p className="text-sm text-muted-foreground">
                                {item.category !== '' && `${item.category} · `}
                                {t(
                                    '{count, plural, one {# block} other {# blocks}}',
                                    { count: item.blocks },
                                )}
                                {item.updatedAt && (
                                    <>
                                        {' · '}
                                        <SiteDate iso={item.updatedAt} />
                                    </>
                                )}
                            </p>
                            <div className="mt-auto flex flex-wrap gap-1.5">
                                <Button asChild variant="outline" size="sm">
                                    <Link href={item.editUrl}>
                                        {item.kind === 'synced' ? (
                                            <Repeat />
                                        ) : (
                                            <Pencil />
                                        )}
                                        {t('Edit {name}', { name: item.title })}
                                    </Link>
                                </Button>
                                <Button asChild variant="ghost" size="sm">
                                    <a href={`${exportUrl}?ids[]=${item.id}`}>
                                        <Download />
                                        {t('Export')}
                                    </a>
                                </Button>
                            </div>
                        </li>
                    ))}
                    {shownPatterns.map((pattern) => (
                        <li
                            key={`pattern-${pattern.name}`}
                            className="flex flex-col gap-2 rounded-lg border border-dashed bg-card p-5"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <h2 className="text-base font-semibold">
                                    {pattern.title}
                                </h2>
                                <StatusBadge tone="neutral">
                                    {t('Theme or plugin')}
                                </StatusBadge>
                            </div>
                            <p className="text-sm text-muted-foreground">
                                {pattern.description || pattern.category}
                            </p>
                        </li>
                    ))}
                </ul>
            )}
        </>
    );
}
