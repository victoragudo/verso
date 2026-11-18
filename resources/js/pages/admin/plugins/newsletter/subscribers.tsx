import { Head, Link, router, useForm } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
import { Pagination } from '@/components/verso/content/pagination';
import {
    DataTable,
    EmptyState,
    FilterTabs,
    PageHeader,
    SectionCard,
    SiteDate,
    StatusBadge,
    statusTone,
} from '@/components/verso/ui';
import type { DataTableColumn } from '@/components/verso/ui';
import { formatList, formatNumber, msg, t } from '@/lib/i18n';
import type { Paginated } from '@/types';

type Status = 'all' | 'pending' | 'subscribed' | 'unsubscribed';

type SubscriberRow = {
    id: number;
    email: string;
    name: string | null;
    status: Exclude<Status, 'all'>;
    source: string;
    lists: string[];
    created_at: string | null;
};

type MailingList = {
    id: number;
    name: string;
    slug: string;
    subscribers_count: number;
};

type Props = {
    subscribers: Paginated<SubscriberRow>;
    filters: { status: Status; search: string; list: number | null };
    counts: Record<Status, number>;
    lists: MailingList[];
    urls: {
        index: string;
        store: string;
        import: string;
        export: string;
        subscriber: string;
        lists: string;
        campaigns: string;
    };
};

const TABS: { key: Status; label: string }[] = [
    { key: 'all', label: msg('All') },
    { key: 'subscribed', label: msg('newsletter::Subscribed') },
    { key: 'pending', label: msg('Pending') },
    { key: 'unsubscribed', label: msg('newsletter::Unsubscribed') },
];

const STATUS_LABELS: Record<SubscriberRow['status'], string> = {
    subscribed: msg('newsletter::Subscribed'),
    pending: msg('Pending'),
    unsubscribed: msg('newsletter::Unsubscribed'),
};

const NO_LIST = 'none';

const STATUS_TONES: Record<SubscriberRow['status'], string> = {
    subscribed: 'active',
    pending: 'pending',
    unsubscribed: 'draft',
};

// Subscribers and lists, with forms to add one person, import a CSV and export all.
export default function SubscribersIndex({
    subscribers,
    filters,
    counts,
    lists,
    urls,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const add = useForm({ email: '', name: '', list_id: NO_LIST });
    const upload = useForm<{
        file: File | null;
        list_id: string;
        consent: boolean;
    }>({
        file: null,
        list_id: NO_LIST,
        consent: false,
    });
    const list = useForm({ name: '' });
    const subscriberUrl = (id: number) =>
        urls.subscriber.replace('__ID__', String(id));
    const query = (
        values: Partial<{
            status: Status;
            search: string;
            list: number | null;
        }>,
    ) => {
        const merged = { ...filters, ...values };
        const params = new URLSearchParams({
            status: merged.status,
            search: merged.search,
        });

        if (merged.list) {
            params.set('list', String(merged.list));
        }

        return `${urls.index}?${params.toString()}`;
    };

    add.transform((values) => ({
        ...values,
        list_id: values.list_id === NO_LIST ? null : Number(values.list_id),
    }));
    upload.transform((values) => ({
        ...values,
        list_id: values.list_id === NO_LIST ? null : Number(values.list_id),
    }));

    const submitAdd = (event: FormEvent) => {
        event.preventDefault();
        add.post(urls.store, {
            preserveScroll: true,
            onSuccess: () => add.reset('email', 'name'),
        });
    };

    const submitImport = (event: FormEvent) => {
        event.preventDefault();
        upload.post(urls.import, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => upload.reset(),
        });
    };

    const submitList = (event: FormEvent) => {
        event.preventDefault();
        list.post(urls.lists, {
            preserveScroll: true,
            onSuccess: () => list.reset(),
        });
    };

    const listSelect = (
        id: string,
        value: string,
        onChange: (value: string) => void,
    ) => (
        <Select value={value} onValueChange={onChange}>
            <SelectTrigger id={id} className="w-full">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={NO_LIST}>
                    {t('newsletter::No list')}
                </SelectItem>
                {lists.map((item) => (
                    <SelectItem key={item.id} value={String(item.id)}>
                        {item.name}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );

    const columns: DataTableColumn<SubscriberRow>[] = [
        {
            key: 'email',
            header: t('newsletter::Subscriber'),
            primary: true,
            className: 'max-w-0 w-1/2',
            cell: (row) => (
                <div className="min-w-0">
                    <div className="truncate font-medium text-foreground">
                        {row.email}
                    </div>
                    {row.name && (
                        <div className="truncate text-xs text-muted-foreground">
                            {row.name}
                        </div>
                    )}
                </div>
            ),
        },
        {
            key: 'status',
            header: t('Status'),
            cell: (row) => (
                <StatusBadge tone={statusTone(STATUS_TONES[row.status])}>
                    {t(STATUS_LABELS[row.status])}
                </StatusBadge>
            ),
        },
        {
            key: 'lists',
            header: t('newsletter::Lists'),
            hideOnMobile: true,
            className: 'text-muted-foreground',
            cell: (row) => (row.lists.length ? formatList(row.lists) : '-'),
        },
        {
            key: 'created',
            header: t('newsletter::Joined'),
            className: 'whitespace-nowrap text-muted-foreground',
            cell: (row) => <SiteDate iso={row.created_at} dayOnly />,
        },
    ];

    return (
        <>
            <Head title={t('newsletter::Subscribers')} />
            <PageHeader
                icon="newsletter"
                title={t('newsletter::Subscribers')}
                description={t(
                    'newsletter::People confirm their address by email before they get campaigns.',
                )}
                actions={
                    <>
                        <Button asChild variant="outline">
                            <a href={urls.export}>
                                {t('newsletter::Export CSV')}
                            </a>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href={urls.campaigns}>
                                {t('newsletter::Campaigns')}
                            </Link>
                        </Button>
                    </>
                }
            />

            <div className="grid items-start gap-4 @3xl/page:grid-cols-[clamp(20rem,22cqi,28rem)_minmax(0,1fr)]">
                <div className="grid gap-4">
                    <SectionCard
                        title={t('newsletter::Add subscriber')}
                        description={t(
                            'newsletter::They get a confirmation email.',
                        )}
                    >
                        <form onSubmit={submitAdd} className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="add-email">{t('Email')}</Label>
                                <Input
                                    id="add-email"
                                    type="email"
                                    value={add.data.email}
                                    onChange={(event) =>
                                        add.setData('email', event.target.value)
                                    }
                                    placeholder="reader@example.com"
                                    required
                                    autoComplete="off"
                                    spellCheck={false}
                                />
                                <InputError message={add.errors.email} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="add-name">{t('Name')}</Label>
                                <Input
                                    id="add-name"
                                    value={add.data.name}
                                    onChange={(event) =>
                                        add.setData('name', event.target.value)
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="add-list">
                                    {t('newsletter::List')}
                                </Label>
                                {listSelect(
                                    'add-list',
                                    add.data.list_id,
                                    (value) => add.setData('list_id', value),
                                )}
                            </div>
                            <Button type="submit" disabled={add.processing}>
                                {t('newsletter::Add subscriber')}
                            </Button>
                        </form>
                    </SectionCard>

                    <SectionCard
                        title={t('newsletter::Import CSV')}
                        description={t(
                            'newsletter::A column named email, and optionally name.',
                        )}
                    >
                        <form onSubmit={submitImport} className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="import-file">{t('File')}</Label>
                                <Input
                                    id="import-file"
                                    type="file"
                                    accept=".csv,text/csv"
                                    onChange={(event) =>
                                        upload.setData(
                                            'file',
                                            event.target.files?.[0] ?? null,
                                        )
                                    }
                                    required
                                />
                                <InputError message={upload.errors.file} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="import-list">
                                    {t('newsletter::Add to list')}
                                </Label>
                                {listSelect(
                                    'import-list',
                                    upload.data.list_id,
                                    (value) => upload.setData('list_id', value),
                                )}
                            </div>
                            <label className="flex items-start gap-2.5 text-sm">
                                <Checkbox
                                    className="mt-0.5"
                                    checked={upload.data.consent}
                                    onCheckedChange={(value) =>
                                        upload.setData(
                                            'consent',
                                            value === true,
                                        )
                                    }
                                />
                                {t(
                                    'newsletter::These people agreed to get the newsletter.',
                                )}
                            </label>
                            <InputError message={upload.errors.consent} />
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={upload.processing}
                            >
                                {t('Import')}
                            </Button>
                        </form>
                    </SectionCard>

                    <SectionCard
                        title={t('newsletter::Lists')}
                        contentClassName="grid gap-3"
                    >
                        {lists.length > 0 && (
                            <ul className="grid gap-1 text-sm">
                                {lists.map((item) => (
                                    <li
                                        key={item.id}
                                        className="flex min-h-10 items-center justify-between gap-2"
                                    >
                                        <Link
                                            href={query({ list: item.id })}
                                            className="truncate hover:underline"
                                        >
                                            {item.name}
                                        </Link>
                                        <span className="flex items-center gap-2">
                                            <span className="text-muted-foreground tabular-nums">
                                                {formatNumber(
                                                    item.subscribers_count,
                                                )}
                                            </span>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => {
                                                    if (
                                                        window.confirm(
                                                            t(
                                                                'newsletter::Delete the list {list}? Its subscribers stay.',
                                                                {
                                                                    list: item.name,
                                                                },
                                                            ),
                                                        )
                                                    ) {
                                                        router.delete(
                                                            urls.lists +
                                                                '/' +
                                                                item.id,
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        );
                                                    }
                                                }}
                                            >
                                                {t('Delete')}
                                            </Button>
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                        <form onSubmit={submitList} className="flex gap-2">
                            <Input
                                value={list.data.name}
                                onChange={(event) =>
                                    list.setData('name', event.target.value)
                                }
                                placeholder={t('newsletter::Weekly digest')}
                                aria-label={t('newsletter::New list name')}
                                required
                            />
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={list.processing}
                            >
                                {t('Add')}
                            </Button>
                        </form>
                        <InputError
                            message={
                                list.errors.name ??
                                (list.errors as Record<string, string>).slug
                            }
                        />
                    </SectionCard>
                </div>

                <div className="flex min-w-0 flex-col gap-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <FilterTabs
                            active={filters.status}
                            tabs={TABS.map((tab) => ({
                                key: tab.key,
                                label: t(tab.label),
                                href: query({ status: tab.key }),
                                count: counts[tab.key],
                            }))}
                        />
                        <form
                            role="search"
                            className="relative w-full sm:w-64"
                            onSubmit={(event) => {
                                event.preventDefault();
                                router.get(
                                    query({ search }),
                                    {},
                                    { preserveState: true },
                                );
                            }}
                        >
                            <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                type="search"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder={t(
                                    'newsletter::Search email or name',
                                )}
                                aria-label={t('newsletter::Search subscribers')}
                                className="ps-9"
                            />
                        </form>
                    </div>

                    <DataTable
                        rows={subscribers.data}
                        columns={columns}
                        rowKey={(row) => row.id}
                        caption={t('newsletter::Subscribers')}
                        rowLabel={(row) => row.email}
                        rowActions={(row) => [
                            ...(row.status === 'unsubscribed'
                                ? []
                                : [
                                      {
                                          label: t('newsletter::Unsubscribe'),
                                          onSelect: () => {
                                              if (
                                                  window.confirm(
                                                      t(
                                                          'newsletter::Unsubscribe {email}? They stop getting your emails.',
                                                          { email: row.email },
                                                      ),
                                                  )
                                              ) {
                                                  router.put(
                                                      `${subscriberUrl(row.id)}/unsubscribe`,
                                                      {},
                                                      { preserveScroll: true },
                                                  );
                                              }
                                          },
                                      },
                                  ]),
                            {
                                label: t('Delete'),
                                destructive: true,
                                onSelect: () => {
                                    if (
                                        window.confirm(
                                            t(
                                                'newsletter::Delete {email} and its history?',
                                                { email: row.email },
                                            ),
                                        )
                                    ) {
                                        router.delete(subscriberUrl(row.id), {
                                            preserveScroll: true,
                                        });
                                    }
                                },
                            },
                        ]}
                        empty={
                            <EmptyState
                                icon="newsletter"
                                title={
                                    filters.search
                                        ? t(
                                              'newsletter::No subscribers match “{search}”.',
                                              { search: filters.search },
                                          )
                                        : t(
                                              'newsletter::No subscribers here yet.',
                                          )
                                }
                                description={t(
                                    'newsletter::Add the Newsletter signup block to a page, or import a CSV.',
                                )}
                            />
                        }
                    />
                    <Pagination page={subscribers} />
                </div>
            </div>
        </>
    );
}

SubscribersIndex.layout = {
    breadcrumbs: [
        {
            title: msg('newsletter::Subscribers'),
            href: '/admin/plugins/newsletter/subscribers',
        },
    ],
};
