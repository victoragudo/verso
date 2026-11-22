import { Head, Link, router } from '@inertiajs/react';
import { Plus, RotateCcw, Search, Trash } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { inlineLabel } from '@/components/verso/content/inline-label';
import { Pagination } from '@/components/verso/content/pagination';
import { EntryStatusBadge } from '@/components/verso/content/status-badge';
import {
    SlotItemView,
    slotRowActions,
    useSlotItems,
} from '@/components/verso/slots';
import {
    chooserColumns,
    ColumnChooser,
    DataTable,
    EmptyState,
    FilterTabs,
    ListCell,
    listCellClass,
    PageHeader,
    SiteDate,
    useListView,
} from '@/components/verso/ui';
import type {
    DataTableColumn,
    ListViewState,
    RowAction,
    ServerListColumn,
} from '@/components/verso/ui';
import { msg, t } from '@/lib/i18n';
import {
    bulk,
    create,
    destroy,
    edit,
    index,
    restore,
} from '@/routes/admin/entries';
import type {
    ContentTypeInfo,
    EntryAuthor,
    EntryStatus,
    Paginated,
} from '@/types';

type EntryRow = {
    id: number;
    title: string;
    slug: string;
    status: EntryStatus;
    author: EntryAuthor | null;
    published_at: string | null;
    updated_at: string | null;
    url: string;
    // Values of the optional columns, by column key.
    columns?: Record<string, unknown>;
    can: { update: boolean; delete: boolean; restore: boolean };
};

type Props = {
    contentType: ContentTypeInfo;
    entries: Paginated<EntryRow>;
    filters: { status: EntryStatus | null; search: string };
    counts: Record<'all' | EntryStatus, number>;
    can: { create: boolean };
    columns: ServerListColumn[];
    listView: ListViewState;
};

const TABS: { key: 'all' | EntryStatus; label: string }[] = [
    { key: 'all', label: msg('All') },
    { key: 'published', label: msg('Published') },
    { key: 'scheduled', label: msg('Scheduled') },
    { key: 'draft', label: msg('Drafts') },
    { key: 'trash', label: msg('Trash') },
];

// Lists the entries of one content type with filters, search and bulk actions.
export default function EntriesIndex({
    contentType,
    entries,
    filters,
    counts,
    can,
    columns: serverColumns,
    listView,
}: Props) {
    const view = useListView(listView);
    const [search, setSearch] = useState(filters.search);
    const [selected, setSelected] = useState<number[]>([]);
    const slotColumns = useSlotItems('entries-columns');
    const slotActions = useSlotItems('entries-row-actions');
    const type = contentType.name;
    const inTrash = filters.status === 'trash';

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            index.url(type, {
                query: { status: filters.status ?? undefined, search },
            }),
            {},
            { preserveState: true },
        );
    };

    const runBulk = (action: 'trash' | 'restore') => {
        router.post(
            bulk.url(type),
            { action, ids: selected },
            { preserveScroll: true, onSuccess: () => setSelected([]) },
        );
    };

    // Optional columns from the server, like categories, word count or price.
    const optional = (keys: (column: ServerListColumn) => boolean) =>
        serverColumns.filter(keys).map((column): DataTableColumn<EntryRow> => ({
            key: column.key,
            header: column.label,
            label: column.label,
            optional: true,
            defaultFrom: column.defaultFrom,
            className: listCellClass(column.kind),
            cell: (entry) => (
                <ListCell
                    kind={column.kind}
                    value={entry.columns?.[column.key]}
                />
            ),
        }));

    const columns: DataTableColumn<EntryRow>[] = [
        ...optional((column) => column.kind === 'image').map((column) => ({
            ...column,
            header: <span className="sr-only">{column.label}</span>,
        })),
        {
            key: 'title',
            header: t('Title'),
            primary: true,
            // The title keeps a sensible width, so wide lists have room for more columns.
            className:
                'w-[45%] max-w-0 @min-[80rem]/table:w-[32%] @min-[125rem]/table:w-[24%]',
            cell: (entry) =>
                entry.can.update ? (
                    <Link
                        href={edit({ contentType: type, entry: entry.id })}
                        className="block truncate font-medium text-foreground hover:text-brand"
                        title={entryTitle(entry)}
                    >
                        <bdi>{entryTitle(entry)}</bdi>
                    </Link>
                ) : (
                    <span
                        className="block truncate font-medium text-foreground"
                        title={entryTitle(entry)}
                    >
                        <bdi>{entryTitle(entry)}</bdi>
                    </span>
                ),
        },
        {
            key: 'status',
            header: t('Status'),
            cell: (entry) => <EntryStatusBadge status={entry.status} />,
        },
        ...optional((column) => column.kind !== 'image'),
        {
            key: 'author',
            header: t('Author'),
            label: t('Author'),
            optional: true,
            defaultFrom: 'md',
            className: 'whitespace-nowrap',
            cell: (entry) => entry.author?.name ?? t('Nobody'),
        },
        {
            key: 'date',
            header: t('Date'),
            className: 'whitespace-nowrap text-muted-foreground',
            cell: (entry) => (
                <SiteDate iso={entry.published_at ?? entry.updated_at} />
            ),
        },
        ...slotColumns.map((item): DataTableColumn<EntryRow> => ({
            key: `slot-${item.key}`,
            header: item.label ?? '',
            hideOnMobile: true,
            cell: (entry) => (
                <SlotItemView<'entries-columns'>
                    item={item}
                    context={{ row: entry, contentType }}
                />
            ),
        })),
    ];

    const tabs = TABS.map((tab) => ({
        key: tab.key,
        label: t(tab.label),
        count: counts[tab.key],
        href: index.url(type, {
            query: { status: tab.key === 'all' ? undefined : tab.key },
        }),
    }));

    const plural = inlineLabel(contentType.pluralLabel);
    const newEntryLabel = t('New {type}', {
        type: inlineLabel(contentType.label),
    });

    return (
        <>
            <Head title={contentType.pluralLabel} />
            <PageHeader
                icon={contentType.icon}
                title={contentType.pluralLabel}
                description={t('Write, schedule and organize your {type}.', {
                    type: plural,
                })}
                actions={
                    can.create && (
                        <Button asChild>
                            <Link href={create(type)}>
                                <Plus />
                                {newEntryLabel}
                            </Link>
                        </Button>
                    )
                }
            />

            <div className="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <FilterTabs
                    tabs={tabs}
                    active={filters.status ?? 'all'}
                    label={t('Filter {type}', { type: plural })}
                />
                <div className="flex w-full items-center gap-2 lg:w-auto">
                    <form
                        onSubmit={submitSearch}
                        role="search"
                        className="relative min-w-0 flex-1 lg:w-72 lg:flex-none"
                    >
                        <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={t('Search {type}', { type: plural })}
                            aria-label={t('Search {type}', { type: plural })}
                            className="ps-9"
                        />
                    </form>
                    <ColumnChooser
                        columns={chooserColumns(columns)}
                        visible={view.columns}
                        onChange={view.setColumns}
                        density={view.density}
                        onDensityChange={view.setDensity}
                    />
                </div>
            </div>

            {selected.length > 0 && (
                <div className="mb-3 flex flex-wrap items-center gap-2 rounded-lg border bg-card px-3 py-2 text-sm shadow-xs">
                    <span className="font-medium text-foreground tabular-nums">
                        {t('{count, number} selected', {
                            count: selected.length,
                        })}
                    </span>
                    {inTrash ? (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => runBulk('restore')}
                        >
                            <RotateCcw />
                            {t('Restore')}
                        </Button>
                    ) : (
                        <Button
                            size="sm"
                            variant="outline"
                            className="text-destructive-foreground"
                            onClick={() => runBulk('trash')}
                        >
                            <Trash />
                            {t('Move to trash')}
                        </Button>
                    )}
                    <Button
                        size="sm"
                        variant="ghost"
                        className="ms-auto"
                        onClick={() => setSelected([])}
                    >
                        {t('Clear')}
                    </Button>
                </div>
            )}

            <DataTable
                rows={entries.data}
                columns={columns}
                visibleColumns={view.columns}
                density={view.density}
                rowKey={(entry) => entry.id}
                caption={contentType.pluralLabel}
                selection={{
                    selected,
                    onChange: setSelected,
                    label: (entry) =>
                        t('Select {title}', { title: entryTitle(entry) }),
                }}
                rowLabel={entryTitle}
                rowActions={(entry) => [
                    ...rowActions(type, entry),
                    ...slotRowActions(slotActions, {
                        row: entry,
                        contentType,
                    }),
                ]}
                empty={
                    <EmptyState
                        icon={contentType.icon}
                        title={
                            filters.search
                                ? t('No {type} match “{search}”.', {
                                      type: plural,
                                      search: filters.search,
                                  })
                                : t('No {type} here yet.', { type: plural })
                        }
                        action={
                            can.create &&
                            !inTrash && (
                                <Button asChild variant="outline">
                                    <Link href={create(type)}>
                                        <Plus />
                                        {newEntryLabel}
                                    </Link>
                                </Button>
                            )
                        }
                    />
                }
            />

            <div className="mt-4">
                <Pagination page={entries} />
            </div>
        </>
    );
}

function entryTitle(entry: EntryRow): string {
    return entry.title || t('Untitled');
}

// The actions in the row menu, based on what the user may do.
function rowActions(type: string, entry: EntryRow): RowAction[] {
    const params = { contentType: type, entry: entry.id };

    return [
        {
            label: t('Edit'),
            href: edit.url(params),
            hidden: !entry.can.update,
        },
        {
            label: t('View on site'),
            href: entry.url,
            external: true,
            hidden: entry.status !== 'published',
        },
        {
            label: t('Restore'),
            href: restore.url(params),
            method: 'post',
            hidden: !entry.can.restore,
        },
        {
            label: t('Move to trash'),
            href: destroy.url(params),
            method: 'delete',
            destructive: true,
            hidden: !entry.can.delete,
        },
    ];
}

EntriesIndex.layout = (props: Props) => ({
    breadcrumbs: [
        {
            title: props.contentType.pluralLabel,
            href: index(props.contentType.name),
        },
    ],
});
