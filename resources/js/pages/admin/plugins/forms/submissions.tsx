import { Head, Link, router, useForm } from '@inertiajs/react';
import { Download, Search } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pagination } from '@/components/verso/content/pagination';
import { STATUS_TONES, statusLabel } from '@/components/verso/forms/status';
import type {
    SubmissionRow,
    SubmissionStatus,
} from '@/components/verso/forms/types';
import {
    chooserColumns,
    ColumnChooser,
    DataTable,
    DatePicker,
    EmptyState,
    FilterTabs,
    ListCell,
    PageHeader,
    SearchableSelect,
    SiteDate,
    StatusBadge,
    useListView,
} from '@/components/verso/ui';
import type { DataTableColumn, ListViewState } from '@/components/verso/ui';
import { msg, t } from '@/lib/i18n';
import type { Paginated } from '@/types';

type Tab = 'inbox' | SubmissionStatus;

type Props = {
    submissions: Paginated<SubmissionRow>;
    filters: {
        status: Tab;
        search: string;
        form: number | null;
        since: string;
    };
    counts: Record<Tab, number>;
    forms: { value: string; label: string }[];
    urls: {
        index: string;
        forms: string;
        bulk: string;
        erase: string;
        export: string | null;
    };
    listView: ListViewState;
};

const ALL_FORMS = 'all';

const TABS: { key: Tab; label: string }[] = [
    { key: 'inbox', label: msg('forms::Inbox') },
    { key: 'new', label: msg('forms::New') },
    { key: 'read', label: msg('forms::Read') },
    { key: 'archived', label: msg('forms::Archived') },
    { key: 'spam', label: msg('forms::Spam') },
];

const BULK_ACTIONS: { action: SubmissionStatus | 'delete'; label: string }[] = [
    { action: 'read', label: msg('forms::Mark read') },
    { action: 'archived', label: msg('forms::Archive') },
    { action: 'spam', label: msg('forms::Mark as spam') },
    { action: 'new', label: msg('forms::Not spam') },
    { action: 'delete', label: msg('Delete') },
];

// The messages people sent with the forms: filters, bulk actions, export and privacy requests.
export default function SubmissionsIndex({
    submissions,
    filters,
    counts,
    forms,
    urls,
    listView,
}: Props) {
    const view = useListView(listView);
    const [search, setSearch] = useState(filters.search);
    const [selected, setSelected] = useState<number[]>([]);
    const [eraseOpen, setEraseOpen] = useState(false);
    const erase = useForm({ email: '' });

    const query = (changes: Record<string, string | number | null>) => {
        const params = new URLSearchParams();
        const merged = {
            status: filters.status === 'inbox' ? null : filters.status,
            search: filters.search || null,
            form: filters.form,
            since: filters.since || null,
            ...changes,
        };

        Object.entries(merged).forEach(([key, value]) => {
            if (value !== null && value !== '' && value !== 'inbox') {
                params.set(key, String(value));
            }
        });

        const text = params.toString();

        return text ? `${urls.index}?${text}` : urls.index;
    };

    const visit = (changes: Record<string, string | number | null>) =>
        router.get(query(changes), {}, { preserveState: true });

    const runBulk = (action: SubmissionStatus | 'delete') => {
        if (
            action === 'delete' &&
            !window.confirm(
                t(
                    'forms::{count, plural, one {Delete # message and its files?} other {Delete # messages and their files?}}',
                    { count: selected.length },
                ),
            )
        ) {
            return;
        }

        router.post(
            urls.bulk,
            { ids: selected, action },
            { preserveScroll: true, onSuccess: () => setSelected([]) },
        );
    };

    const submitErase = (event: FormEvent) => {
        event.preventDefault();
        erase.post(urls.erase, {
            onSuccess: () => {
                setEraseOpen(false);
                erase.reset();
            },
        });
    };

    const columns: DataTableColumn<SubmissionRow>[] = [
        {
            key: 'sender',
            header: t('forms::From'),
            primary: true,
            className: 'max-w-0 w-1/3',
            cell: (row) => (
                <Link href={row.url} className="block min-w-0 hover:underline">
                    <span
                        className={
                            row.status === 'new'
                                ? 'block truncate font-semibold text-foreground'
                                : 'block truncate font-medium text-foreground'
                        }
                    >
                        {row.name || row.email || t('forms::No name')}
                    </span>
                    {row.name && row.email && (
                        <span className="block truncate text-xs text-muted-foreground">
                            {row.email}
                        </span>
                    )}
                </Link>
            ),
        },
        {
            key: 'preview',
            header: t('forms::Message'),
            className: 'max-w-0 w-1/2 text-muted-foreground',
            cell: (row) => (
                <span className="block truncate">
                    <span className="text-foreground">{row.form.title}</span>
                    {row.preview && ` · ${row.preview}`}
                </span>
            ),
        },
        {
            key: 'status',
            header: t('Status'),
            cell: (row) => (
                <StatusBadge tone={STATUS_TONES[row.status]}>
                    {statusLabel(row.status)}
                </StatusBadge>
            ),
        },
        {
            key: 'date',
            header: t('forms::Sent'),
            hideOnMobile: true,
            className: 'whitespace-nowrap text-muted-foreground',
            cell: (row) => <SiteDate iso={row.created_at} />,
        },
        {
            key: 'form',
            header: t('forms::Form'),
            label: t('forms::Form'),
            optional: true,
            defaultFrom: 'xl',
            className: 'whitespace-nowrap',
            cell: (row) => row.form.title,
        },
        {
            key: 'email',
            header: t('forms::Email'),
            label: t('forms::Email'),
            optional: true,
            defaultFrom: '2xl',
            className: 'whitespace-nowrap',
            cell: (row) => <ListCell kind="text" value={row.email} />,
        },
        {
            key: 'files',
            header: t('forms::Files'),
            label: t('forms::Files'),
            optional: true,
            defaultFrom: '2xl',
            className: 'text-end whitespace-nowrap',
            cell: (row) => <ListCell kind="number" value={row.files ?? 0} />,
        },
    ];

    return (
        <>
            <Head title={t('forms::Form inbox')} />
            <PageHeader
                icon="forms"
                title={t('forms::Form inbox')}
                description={t(
                    'forms::Messages sent with your forms. Spam stays apart from the inbox.',
                )}
                actions={
                    <>
                        <Button
                            variant="outline"
                            onClick={() => setEraseOpen(true)}
                        >
                            {t('forms::Privacy request')}
                        </Button>
                        {urls.export ? (
                            <Button asChild variant="outline">
                                <a href={urls.export}>
                                    <Download className="size-4" />
                                    {t('forms::Export CSV')}
                                </a>
                            </Button>
                        ) : null}
                        <Button asChild variant="outline">
                            <Link href={urls.forms}>{t('forms::Forms')}</Link>
                        </Button>
                        <ColumnChooser
                            columns={chooserColumns(columns)}
                            visible={view.columns}
                            onChange={view.setColumns}
                            density={view.density}
                            onDensityChange={view.setDensity}
                        />
                    </>
                }
            />

            <div className="flex min-w-0 flex-col gap-4">
                <FilterTabs
                    active={filters.status}
                    tabs={TABS.map((tab) => ({
                        ...tab,
                        label: t(tab.label),
                        href: query({
                            status: tab.key === 'inbox' ? null : tab.key,
                        }),
                        count: counts[tab.key],
                    }))}
                />

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-[14rem_12rem_minmax(0,40rem)]">
                    <div className="grid gap-1.5">
                        <Label htmlFor="filter-form">{t('forms::Form')}</Label>
                        <SearchableSelect
                            id="filter-form"
                            value={
                                filters.form ? String(filters.form) : ALL_FORMS
                            }
                            onValueChange={(value) =>
                                visit({
                                    form: value === ALL_FORMS ? null : value,
                                    page: null,
                                })
                            }
                            options={[
                                {
                                    value: ALL_FORMS,
                                    label: t('forms::All forms'),
                                },
                                ...forms,
                            ]}
                            searchPlaceholder={t('forms::Search forms')}
                            className="w-full"
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="filter-since">
                            {t('forms::Sent since')}
                        </Label>
                        <DatePicker
                            id="filter-since"
                            value={filters.since}
                            onChange={(value) =>
                                visit({
                                    since: value.slice(0, 10) || null,
                                    page: null,
                                })
                            }
                            placeholder={t('forms::Any day')}
                            className="w-full"
                        />
                    </div>
                    <form
                        role="search"
                        className="grid gap-1.5 sm:col-span-2 lg:col-span-1"
                        onSubmit={(event) => {
                            event.preventDefault();
                            visit({ search: search || null, page: null });
                        }}
                    >
                        <Label htmlFor="filter-search">{t('Search')}</Label>
                        <div className="relative">
                            <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                id="filter-search"
                                type="search"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder={t(
                                    'forms::Name, email or words in the message',
                                )}
                                className="ps-9"
                            />
                        </div>
                    </form>
                </div>

                {selected.length > 0 && (
                    <div
                        className="flex flex-wrap items-center gap-2 rounded-lg border bg-card px-3 py-2 shadow-xs"
                        role="region"
                        aria-label={t('forms::Actions for the chosen messages')}
                    >
                        <span className="me-1 text-sm tabular-nums">
                            {t('forms::{count, number} chosen', {
                                count: selected.length,
                            })}
                        </span>
                        {BULK_ACTIONS.map((item) => (
                            <Button
                                key={item.action}
                                size="sm"
                                variant={
                                    item.action === 'delete'
                                        ? 'ghost'
                                        : 'outline'
                                }
                                className={
                                    item.action === 'delete'
                                        ? 'text-danger-text'
                                        : undefined
                                }
                                onClick={() => runBulk(item.action)}
                            >
                                {t(item.label)}
                            </Button>
                        ))}
                    </div>
                )}

                <DataTable
                    rows={submissions.data}
                    columns={columns}
                    visibleColumns={view.columns}
                    density={view.density}
                    rowKey={(row) => row.id}
                    caption={t('forms::Form messages')}
                    rowLabel={(row) =>
                        row.name ||
                        row.email ||
                        t('forms::Message {id}', { id: row.id })
                    }
                    selection={{
                        selected,
                        onChange: setSelected,
                        label: (row) =>
                            t('forms::Choose the message from {sender}', {
                                sender: row.name || row.email || row.id,
                            }),
                    }}
                    rowActions={(row) => [
                        { label: t('forms::Open'), href: row.url },
                        {
                            label:
                                row.status === 'spam'
                                    ? t('forms::Not spam')
                                    : t('forms::Mark as spam'),
                            onSelect: () =>
                                router.post(
                                    urls.bulk,
                                    {
                                        ids: [row.id],
                                        action:
                                            row.status === 'spam'
                                                ? 'new'
                                                : 'spam',
                                    },
                                    { preserveScroll: true },
                                ),
                        },
                    ]}
                    empty={
                        <EmptyState
                            icon="forms"
                            title={
                                filters.search
                                    ? t(
                                          'forms::No messages match “{search}”.',
                                          {
                                              search: filters.search,
                                          },
                                      )
                                    : t('forms::No messages here.')
                            }
                            description={t(
                                'forms::Messages show up here when people send your forms.',
                            )}
                        />
                    }
                />
                <Pagination page={submissions} />
            </div>

            <Dialog open={eraseOpen} onOpenChange={setEraseOpen}>
                <DialogContent>
                    <form onSubmit={submitErase} className="grid gap-4">
                        <DialogHeader>
                            <DialogTitle>
                                {t('forms::Delete a person’s messages')}
                            </DialogTitle>
                            <DialogDescription>
                                {t(
                                    'forms::For privacy requests: every message sent from this email, with its files, is deleted for good.',
                                )}
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-2">
                            <Label htmlFor="erase-email">
                                {t('forms::Email')}
                            </Label>
                            <Input
                                id="erase-email"
                                type="email"
                                required
                                autoComplete="off"
                                autoCapitalize="none"
                                autoCorrect="off"
                                spellCheck={false}
                                value={erase.data.email}
                                onChange={(event) =>
                                    erase.setData('email', event.target.value)
                                }
                                aria-invalid={Boolean(erase.errors.email)}
                            />
                            <InputError message={erase.errors.email} />
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setEraseOpen(false)}
                            >
                                {t('Cancel')}
                            </Button>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={erase.processing}
                            >
                                {t('forms::Delete messages')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

SubmissionsIndex.layout = {
    breadcrumbs: [
        { title: msg('forms::Forms'), href: '/admin/forms' },
        { title: msg('forms::Inbox'), href: '/admin/forms/submissions' },
    ],
};
