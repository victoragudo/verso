import { Head, Link, router } from '@inertiajs/react';
import { Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { CommentStatusBadge } from '@/components/verso/comments/comment-status-badge';
import { Pagination } from '@/components/verso/content/pagination';
import {
    chooserColumns,
    ColumnChooser,
    DataTable,
    EmptyState,
    FilterTabs,
    ListCell,
    PageHeader,
    SiteDate,
    useListView,
} from '@/components/verso/ui';
import type {
    DataTableColumn,
    ListViewState,
    RowAction,
} from '@/components/verso/ui';
import { msg, t } from '@/lib/i18n';
import { bulk, index } from '@/routes/admin/comments';
import type { AdminComment, CommentStatus, Paginated } from '@/types';

type BulkAction =
    | 'approve'
    | 'unapprove'
    | 'spam'
    | 'trash'
    | 'restore'
    | 'delete';

type Props = {
    comments: Paginated<AdminComment>;
    filters: { status: CommentStatus | null; search: string };
    counts: Record<'all' | CommentStatus, number>;
    listView: ListViewState;
};

const TABS: { key: 'all' | CommentStatus; label: string }[] = [
    { key: 'all', label: msg('All') },
    { key: 'pending', label: msg('Pending') },
    { key: 'approved', label: msg('Approved') },
    { key: 'spam', label: msg('Spam') },
    { key: 'trash', label: msg('Trash') },
];

const ACTION_LABELS: Record<BulkAction, string> = {
    approve: msg('Approve'),
    unapprove: msg('Unapprove'),
    spam: msg('Mark as spam'),
    trash: msg('Move to trash'),
    restore: msg('Restore'),
    delete: msg('Delete for good'),
};

// Who wrote it and whether it answers another comment, like "Reply by a guest".
function commentKind(comment: AdminComment): string {
    if (comment.parent_id) {
        return comment.is_member
            ? t('Reply by a member')
            : t('Reply by a guest');
    }

    return comment.is_member
        ? t('Comment by a member')
        : t('Comment by a guest');
}

// The bulk actions that make sense in each tab.
function actionsFor(status: CommentStatus | null): BulkAction[] {
    switch (status) {
        case 'spam':
            return ['approve', 'restore', 'delete'];
        case 'trash':
            return ['restore', 'delete'];
        case 'approved':
            return ['unapprove', 'spam', 'trash'];
        default:
            return ['approve', 'spam', 'trash'];
    }
}

// Moderation page: comments by status with counts, search and bulk actions.
export default function CommentsIndex({
    comments,
    filters,
    counts,
    listView,
}: Props) {
    const view = useListView(listView);
    const [search, setSearch] = useState(filters.search);
    const [selected, setSelected] = useState<number[]>([]);
    const actions = actionsFor(filters.status);

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            index.url({
                query: { status: filters.status ?? undefined, search },
            }),
            {},
            { preserveState: true },
        );
    };

    const run = (action: BulkAction, ids: number[]) => {
        if (
            action === 'delete' &&
            !window.confirm(t('Delete these comments for good?'))
        ) {
            return;
        }

        router.post(
            bulk.url(),
            { action, ids },
            { preserveScroll: true, onSuccess: () => setSelected([]) },
        );
    };

    const columns: DataTableColumn<AdminComment>[] = [
        {
            key: 'author',
            header: t('Author'),
            primary: true,
            className: 'max-w-56 align-top',
            cell: (comment) => (
                <div className="min-w-0">
                    <div className="truncate font-medium text-foreground">
                        {comment.author_name}
                    </div>
                    <div className="truncate text-sm font-normal text-muted-foreground">
                        {comment.author_email}
                    </div>
                </div>
            ),
        },
        {
            key: 'comment',
            header: t('Comment'),
            wrap: true,
            className: 'w-full align-top md:whitespace-normal',
            cell: (comment) => (
                <span className="line-clamp-3 text-body">
                    <bdi>{comment.excerpt}</bdi>
                </span>
            ),
        },
        {
            key: 'status',
            header: t('Status'),
            className: 'align-top',
            cell: (comment) => <CommentStatusBadge status={comment.status} />,
        },
        {
            key: 'entry',
            header: t('In reply to'),
            label: t('In reply to'),
            optional: true,
            defaultFrom: 'md',
            className: 'max-w-48 align-top',
            cell: (comment) =>
                comment.entry?.url ? (
                    <a
                        href={comment.entry.url}
                        target="_blank"
                        rel="noreferrer"
                        className="block truncate text-brand hover:underline"
                    >
                        {comment.entry.title}
                    </a>
                ) : (
                    <span className="block truncate">
                        {comment.entry?.title}
                    </span>
                ),
        },
        {
            key: 'date',
            header: t('Date'),
            className: 'whitespace-nowrap align-top text-muted-foreground',
            cell: (comment) => <SiteDate iso={comment.created_at} />,
        },
        {
            key: 'website',
            header: t('Website'),
            label: t('Website'),
            optional: true,
            defaultFrom: 'xl',
            className: 'max-w-48 align-top',
            cell: (comment) =>
                comment.author_url ? (
                    <a
                        href={comment.author_url}
                        target="_blank"
                        rel="noreferrer nofollow"
                        className="block truncate text-brand hover:underline"
                    >
                        {comment.author_url.replace(/^https?:\/\//, '')}
                    </a>
                ) : (
                    <ListCell kind="text" value={null} />
                ),
        },
        {
            key: 'kind',
            header: t('Type'),
            label: t('Type'),
            optional: true,
            defaultFrom: '2xl',
            className: 'whitespace-nowrap align-top text-muted-foreground',
            cell: commentKind,
        },
    ];

    const rowActions = (comment: AdminComment): RowAction[] =>
        actions
            .filter((action) => action !== 'delete')
            .map((action) => ({
                label: t(ACTION_LABELS[action]),
                onSelect: () => run(action, [comment.id]),
            }));

    const tabs = TABS.map((tab) => ({
        key: tab.key,
        label: t(tab.label),
        count: counts[tab.key],
        href: index.url({
            query: { status: tab.key === 'all' ? undefined : tab.key },
        }),
    }));

    return (
        <>
            <Head title={t('Comments')} />
            <PageHeader
                icon="comments"
                title={t('Comments')}
                description={t(
                    'Approve, mark as spam or trash what readers write.',
                )}
            />

            <div className="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <FilterTabs
                    tabs={tabs}
                    active={filters.status ?? 'all'}
                    label={t('Filter comments')}
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
                            placeholder={t('Search comments')}
                            aria-label={t('Search comments')}
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
                        {t(
                            '{count, plural, one {# selected} other {# selected}}',
                            {
                                count: selected.length,
                            },
                        )}
                    </span>
                    {actions.map((action) => (
                        <Button
                            key={action}
                            size="sm"
                            variant="outline"
                            className={
                                action === 'delete'
                                    ? 'text-destructive-foreground'
                                    : undefined
                            }
                            onClick={() => run(action, selected)}
                        >
                            {action === 'delete' && <Trash2 />}
                            {t(ACTION_LABELS[action])}
                        </Button>
                    ))}
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
                rows={comments.data}
                columns={columns}
                visibleColumns={view.columns}
                density={view.density}
                rowKey={(comment) => comment.id}
                caption={t('Comments')}
                selection={{
                    selected,
                    onChange: setSelected,
                    label: (comment) =>
                        t('Select comment by {name}', {
                            name: comment.author_name,
                        }),
                }}
                rowLabel={(comment) =>
                    t('comment by {name}', { name: comment.author_name })
                }
                rowActions={rowActions}
                empty={
                    <EmptyState
                        icon="comments"
                        title={
                            filters.search
                                ? t('No comments match “{search}”.', {
                                      search: filters.search,
                                  })
                                : t('No comments here.')
                        }
                        action={
                            filters.search && (
                                <Button asChild variant="outline">
                                    <Link
                                        href={index.url({
                                            query: {
                                                status:
                                                    filters.status ?? undefined,
                                            },
                                        })}
                                    >
                                        {t('Clear search')}
                                    </Link>
                                </Button>
                            )
                        }
                    />
                }
            />

            <div className="mt-4">
                <Pagination page={comments} />
            </div>
        </>
    );
}

CommentsIndex.layout = () => ({
    breadcrumbs: [{ title: t('Comments'), href: index() }],
});
