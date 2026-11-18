import { Head, router, useForm } from '@inertiajs/react';
import { ArrowRight, Search } from 'lucide-react';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Pagination } from '@/components/verso/content/pagination';
import type { NotFoundRow } from '@/components/verso/seo/types';
import {
    DataTable,
    sortByKey,
    EmptyState,
    NoticeBanner,
    PageHeader,
    SiteDate,
} from '@/components/verso/ui';
import type { DataTableColumn, SortOption } from '@/components/verso/ui';
import { formatNumber, msg, t, Trans } from '@/lib/i18n';
import {
    clear,
    destroy,
    index,
    redirect as redirectRoute,
} from '@/routes/admin/seo/not-found';
import type { Paginated } from '@/types';

type Sort = 'hits' | 'recent' | 'path';

type Props = {
    rows: Paginated<NotFoundRow>;
    filters: { search: string; sort: Sort };
    totals: { paths: number; hits: number };
    settings: { enabled: boolean; retentionDays: number; maxRows: number };
    statusCodes: number[];
    defaultStatus: number;
    can: { manage: boolean };
};

const SORT_LABELS: Record<Sort, string> = {
    hits: msg('Most visits'),
    recent: msg('Latest visit'),
    path: msg('Address A to Z'),
};

// The column each sort orders, for the sortable headers.
const SORT_COLUMNS: Record<Sort, SortOption> = {
    hits: { column: 'hits', direction: 'descending' },
    recent: { column: 'last', direction: 'descending' },
    path: { column: 'path', direction: 'ascending' },
};

const STATUS_LABELS: Record<number, string> = {
    301: msg('301 Permanent'),
    302: msg('302 Temporary'),
};

// The 404 monitor: addresses that were not found, with close pages and a quick redirect.
export default function NotFoundMonitor({
    rows,
    filters,
    totals,
    settings,
    statusCodes,
    defaultStatus,
    can,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [target, setTarget] = useState<NotFoundRow | null>(null);
    const reload = (query: Partial<Props['filters']>) =>
        router.get(
            index.url({ query: { ...filters, ...query } }),
            {},
            { preserveState: true },
        );

    const columns: DataTableColumn<NotFoundRow>[] = [
        {
            key: 'path',
            sortable: true,
            header: t('Address'),
            primary: true,
            className: 'max-w-0 w-2/5',
            cell: (row) => (
                <span
                    className="block truncate font-mono text-xs text-foreground"
                    title={row.path}
                >
                    {row.path}
                </span>
            ),
        },
        {
            key: 'hits',
            sortable: true,
            header: t('Visits'),
            className: 'whitespace-nowrap tabular-nums md:text-end',
            cell: (row) => formatNumber(row.hits),
        },
        {
            key: 'last',
            sortable: true,
            header: t('Last visit'),
            className: 'whitespace-nowrap text-muted-foreground',
            optional: true,
            defaultFrom: 'lg',
            cell: (row) => <SiteDate iso={row.last_seen_at} />,
        },
        {
            key: 'referrer',
            header: t('Came from'),
            className: 'whitespace-nowrap text-muted-foreground',
            optional: true,
            defaultFrom: 'xl',
            cell: (row) => row.referrer_host ?? t('Direct or unknown'),
        },
        {
            key: 'match',
            header: t('Closest page'),
            wrap: true,
            className: 'max-w-0 w-1/3',
            cell: (row) =>
                row.redirect ? (
                    <span className="flex min-w-0 items-center gap-1 text-xs text-muted-foreground">
                        <ArrowRight
                            className="size-3 shrink-0"
                            aria-hidden="true"
                        />
                        <span className="truncate">
                            {t('Redirects to {path}', {
                                path: row.redirect,
                            })}
                        </span>
                    </span>
                ) : row.suggestions[0] ? (
                    <span
                        className="block min-w-0 truncate text-xs"
                        title={row.suggestions[0].path}
                    >
                        {row.suggestions[0].title}{' '}
                        <span className="font-mono text-muted-foreground">
                            {row.suggestions[0].path}
                        </span>
                    </span>
                ) : (
                    <span className="text-xs text-muted-foreground">
                        {t('No close page found')}
                    </span>
                ),
        },
    ];

    return (
        <>
            <Head title={t('404 monitor')} />
            <PageHeader
                icon="redirects"
                title={t('404 monitor')}
                description={
                    <Trans
                        text={t(
                            'Addresses people asked for that do not exist: <num>{paths, plural, one {# address} other {# addresses}}</num>, <num>{hits, plural, one {# visit} other {# visits}}</num>. Kept {days, plural, one {# day} other {# days}} after the last visit. No personal data is stored.',
                            {
                                paths: totals.paths,
                                hits: totals.hits,
                                days: settings.retentionDays,
                            },
                        )}
                        components={{
                            num: (children) => (
                                <span className="tabular-nums">{children}</span>
                            ),
                        }}
                    />
                }
                actions={
                    can.manage &&
                    totals.paths > 0 && (
                        <Button
                            type="button"
                            variant="outline"
                            className="min-h-10 md:min-h-9"
                            onClick={() => {
                                if (
                                    window.confirm(
                                        t(
                                            'Clear the whole list of missing addresses?',
                                        ),
                                    )
                                ) {
                                    router.delete(clear.url(), {
                                        preserveScroll: true,
                                    });
                                }
                            }}
                        >
                            {t('Clear the list')}
                        </Button>
                    )
                }
            />

            {!settings.enabled && (
                <NoticeBanner
                    tone="warning"
                    className="mb-4"
                    title={t('The 404 monitor is off')}
                    description={t(
                        'New missing addresses are not counted. Turn it on in System settings, SEO.',
                    )}
                />
            )}

            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                <form
                    role="search"
                    className="relative w-full sm:w-72"
                    onSubmit={(event) => {
                        event.preventDefault();
                        reload({ search });
                    }}
                >
                    <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder={t('Search addresses')}
                        aria-label={t('Search addresses')}
                        className="ps-9"
                    />
                </form>
                <Select
                    value={filters.sort}
                    onValueChange={(value) => reload({ sort: value as Sort })}
                >
                    <SelectTrigger
                        className="w-full sm:w-44"
                        aria-label={t('Sort')}
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {(Object.keys(SORT_LABELS) as Sort[]).map((sort) => (
                            <SelectItem key={sort} value={sort}>
                                {t(SORT_LABELS[sort])}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            <DataTable
                rows={rows.data}
                columns={columns}
                rowKey={(row) => row.id}
                caption={t('Missing addresses')}
                sort={sortByKey(SORT_COLUMNS, filters.sort, (sort) =>
                    reload({ sort }),
                )}
                rowLabel={(row) => row.path}
                rowActions={(row) =>
                    can.manage
                        ? [
                              {
                                  label: t('Create redirect'),
                                  onSelect: () => setTarget(row),
                              },
                              {
                                  label: t('Remove from the list'),
                                  destructive: true,
                                  onSelect: () =>
                                      router.delete(destroy.url(row.id), {
                                          preserveScroll: true,
                                      }),
                              },
                          ]
                        : []
                }
                empty={
                    <EmptyState
                        icon="redirects"
                        title={
                            filters.search
                                ? t('No addresses match “{search}”.', {
                                      search: filters.search,
                                  })
                                : t('No missing pages so far.')
                        }
                        description={
                            filters.search
                                ? undefined
                                : t(
                                      'When someone asks for an address that does not exist, it shows up here.',
                                  )
                        }
                    />
                }
            />
            <Pagination page={rows} />

            {target && (
                <RedirectDialog
                    row={target}
                    statusCodes={statusCodes}
                    defaultStatus={defaultStatus}
                    onClose={() => setTarget(null)}
                />
            )}
        </>
    );
}

// Sends a missing address to another page with the existing redirects.
function RedirectDialog({
    row,
    statusCodes,
    defaultStatus,
    onClose,
}: {
    row: NotFoundRow;
    statusCodes: number[];
    defaultStatus: number;
    onClose: () => void;
}) {
    const form = useForm({
        to_path: row.suggestions[0]?.path ?? '',
        status_code: String(defaultStatus),
    });

    form.transform((values) => ({
        ...values,
        status_code: Number(values.status_code),
    }));

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(redirectRoute.url(row.id), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>{t('Create redirect')}</DialogTitle>
                        <DialogDescription>
                            <Trans
                                text={t(
                                    'Visitors of <path>{path}</path> will go to the page you pick.',
                                    { path: row.path },
                                )}
                                components={{
                                    path: (children) => (
                                        <span className="font-mono">
                                            {children}
                                        </span>
                                    ),
                                }}
                            />
                        </DialogDescription>
                    </DialogHeader>

                    {row.suggestions.length > 0 && (
                        <fieldset className="grid gap-2">
                            <legend className="mb-1 text-sm font-medium">
                                {t('Close pages')}
                            </legend>
                            {row.suggestions.map((suggestion) => (
                                <Button
                                    key={suggestion.path}
                                    type="button"
                                    variant={
                                        form.data.to_path === suggestion.path
                                            ? 'secondary'
                                            : 'outline'
                                    }
                                    aria-pressed={
                                        form.data.to_path === suggestion.path
                                    }
                                    className="h-auto min-h-10 justify-start py-2 text-start whitespace-normal"
                                    onClick={() =>
                                        form.setData('to_path', suggestion.path)
                                    }
                                >
                                    <span className="grid min-w-0">
                                        <span className="truncate">
                                            {suggestion.title}
                                        </span>
                                        <span className="truncate font-mono text-xs text-muted-foreground">
                                            {suggestion.path}
                                        </span>
                                    </span>
                                </Button>
                            ))}
                        </fieldset>
                    )}

                    <div className="grid gap-2">
                        <Label htmlFor="redirect-to">
                            {t('Send visitors to')}
                        </Label>
                        <Input
                            id="redirect-to"
                            value={form.data.to_path}
                            onChange={(event) =>
                                form.setData('to_path', event.target.value)
                            }
                            placeholder={t('/blog/new-post or https://')}
                            required
                        />
                        <InputError message={form.errors.to_path} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="redirect-status">{t('Type')}</Label>
                        <Select
                            value={form.data.status_code}
                            onValueChange={(value) =>
                                form.setData('status_code', value)
                            }
                        >
                            <SelectTrigger
                                id="redirect-status"
                                className="w-full"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {statusCodes.map((code) => (
                                    <SelectItem key={code} value={String(code)}>
                                        {STATUS_LABELS[code]
                                            ? t(STATUS_LABELS[code])
                                            : code}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.status_code} />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                            className="min-h-10 md:min-h-9"
                        >
                            {t('Cancel')}
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="min-h-10 md:min-h-9"
                        >
                            {t('Create redirect')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

NotFoundMonitor.layout = {
    breadcrumbs: [{ title: msg('404 monitor'), href: index() }],
};
