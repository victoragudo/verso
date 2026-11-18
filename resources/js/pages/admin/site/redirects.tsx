import { Head, router, useForm } from '@inertiajs/react';
import { ArrowRight, Search } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
    sortByKey,
    EmptyState,
    PageHeader,
    SectionCard,
} from '@/components/verso/ui';
import type { DataTableColumn, SortOption } from '@/components/verso/ui';
import { formatNumber, msg, t } from '@/lib/i18n';
import { destroy, index, store } from '@/routes/admin/site/redirects';
import type { Paginated, RedirectRow } from '@/types';

type Sort = 'recent' | 'hits' | 'path';

type Props = {
    redirects: Paginated<RedirectRow>;
    filters: { search: string; sort: Sort };
    statusCodes: number[];
    defaultStatus: number;
    totalHits: number;
};

const SORT_LABELS: Record<Sort, string> = {
    recent: msg('Newest first'),
    hits: msg('Most hits'),
    path: msg('Path A to Z'),
};

// The column each sort orders, for the sortable headers.
const SORT_COLUMNS: Record<Sort, SortOption> = {
    recent: { column: 'created', direction: 'descending' },
    hits: { column: 'hits', direction: 'descending' },
    path: { column: 'from', direction: 'ascending' },
};

const STATUS_LABELS: Record<number, string> = {
    301: msg('301 Permanent'),
    302: msg('302 Temporary'),
};

// Lists redirects with their hit counts, with a form to add one.
export default function RedirectsIndex({
    redirects,
    filters,
    statusCodes,
    defaultStatus,
    totalHits,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const form = useForm({
        from_path: '',
        to_path: '',
        status_code: String(defaultStatus),
    });
    const { data, setData, errors, processing } = form;

    form.transform((values) => ({
        ...values,
        status_code: Number(values.status_code),
    }));

    const reload = (query: { search?: string; sort?: Sort }) =>
        router.get(
            index.url({ query: { ...filters, ...query } }),
            {},
            { preserveState: true },
        );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => form.reset('from_path', 'to_path'),
        });
    };

    const remove = (redirect: RedirectRow) => {
        if (
            window.confirm(
                t('Delete the redirect from {path}?', {
                    path: redirect.from_path,
                }),
            )
        ) {
            router.delete(destroy.url(redirect.id), { preserveScroll: true });
        }
    };

    const columns: DataTableColumn<RedirectRow>[] = [
        {
            key: 'from',
            sortable: true,
            header: t('From'),
            primary: true,
            className: 'max-w-0 w-1/2',
            cell: (redirect) => (
                <span className="block truncate font-mono text-xs text-foreground">
                    {redirect.from_path}
                </span>
            ),
        },
        {
            key: 'to',
            header: t('To'),
            className: 'max-w-0 w-1/2',
            cell: (redirect) => (
                <span className="flex min-w-0 items-center gap-1 font-mono text-xs">
                    <ArrowRight className="size-3 shrink-0 text-muted-foreground" />
                    <span className="truncate">{redirect.to_path}</span>
                </span>
            ),
        },
        {
            key: 'type',
            header: t('Type'),
            className: 'whitespace-nowrap text-muted-foreground',
            cell: (redirect) => redirect.status_code,
        },
        {
            key: 'hits',
            sortable: true,
            header: t('Hits'),
            className: 'whitespace-nowrap md:text-end',
            cell: (redirect) => formatNumber(redirect.hits),
        },
    ];

    return (
        <>
            <Head title={t('Redirects')} />
            <PageHeader
                icon="redirects"
                title={t('Redirects')}
                description={t(
                    'Send visitors of old links to the right page. {count, plural, one {# hit} other {# hits}} so far.',
                    { count: totalHits },
                )}
            />

            <div className="grid items-start gap-4 @3xl/page:grid-cols-[clamp(20rem,22cqi,28rem)_minmax(0,1fr)]">
                <SectionCard
                    title={t('Add redirect')}
                    description={t(
                        'Saving an existing path replaces its target.',
                    )}
                >
                    <form onSubmit={submit} className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="from_path">{t('From path')}</Label>
                            <Input
                                id="from_path"
                                value={data.from_path}
                                onChange={(event) =>
                                    setData('from_path', event.target.value)
                                }
                                placeholder="/old-post"
                                required
                            />
                            <InputError message={errors.from_path} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="to_path">{t('To')}</Label>
                            <Input
                                id="to_path"
                                value={data.to_path}
                                onChange={(event) =>
                                    setData('to_path', event.target.value)
                                }
                                placeholder={t('/blog/new-post or https://')}
                                required
                            />
                            <InputError message={errors.to_path} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="status_code">{t('Type')}</Label>
                            <Select
                                value={data.status_code}
                                onValueChange={(value) =>
                                    setData('status_code', value)
                                }
                            >
                                <SelectTrigger
                                    id="status_code"
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {statusCodes.map((code) => (
                                        <SelectItem
                                            key={code}
                                            value={String(code)}
                                        >
                                            {STATUS_LABELS[code]
                                                ? t(STATUS_LABELS[code])
                                                : code}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.status_code} />
                        </div>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="min-h-10 md:min-h-9"
                        >
                            {t('Add redirect')}
                        </Button>
                    </form>
                </SectionCard>

                <div className="flex min-w-0 flex-col gap-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                reload({ search });
                            }}
                            role="search"
                            className="relative w-full sm:w-64"
                        >
                            <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                type="search"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder={t('Search paths')}
                                aria-label={t('Search paths')}
                                className="ps-9"
                            />
                        </form>
                        <Select
                            value={filters.sort}
                            onValueChange={(value) =>
                                reload({ sort: value as Sort })
                            }
                        >
                            <SelectTrigger
                                className="w-full sm:w-auto sm:min-w-40"
                                aria-label={t('Sort')}
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {(Object.keys(SORT_LABELS) as Sort[]).map(
                                    (sort) => (
                                        <SelectItem key={sort} value={sort}>
                                            {t(SORT_LABELS[sort])}
                                        </SelectItem>
                                    ),
                                )}
                            </SelectContent>
                        </Select>
                    </div>

                    <DataTable
                        rows={redirects.data}
                        columns={columns}
                        rowKey={(redirect) => redirect.id}
                        caption={t('Redirects')}
                        sort={sortByKey(SORT_COLUMNS, filters.sort, (sort) =>
                            reload({ sort }),
                        )}
                        rowLabel={(redirect) => redirect.from_path}
                        rowActions={(redirect) => [
                            {
                                label: t('Delete'),
                                destructive: true,
                                onSelect: () => remove(redirect),
                            },
                        ]}
                        empty={
                            <EmptyState
                                icon="redirects"
                                title={
                                    filters.search
                                        ? t('No redirects match “{search}”.', {
                                              search: filters.search,
                                          })
                                        : t('No redirects yet.')
                                }
                                description={
                                    filters.search
                                        ? undefined
                                        : t(
                                              'Add one with the form to send an old link to a new page.',
                                          )
                                }
                            />
                        }
                    />
                    <Pagination page={redirects} />
                </div>
            </div>
        </>
    );
}

RedirectsIndex.layout = {
    breadcrumbs: [{ title: msg('Redirects'), href: index() }],
};
