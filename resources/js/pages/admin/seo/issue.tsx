import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Pagination } from '@/components/verso/content/pagination';
import { LinkCheckCard } from '@/components/verso/seo/link-check-card';
import { affectedText, SEVERITY_TONES } from '@/components/verso/seo/types';
import type {
    LinkCheckState,
    SeoCheckRow,
    SeoIssueItem,
} from '@/components/verso/seo/types';
import {
    DataTable,
    EmptyState,
    PageHeader,
    StatusBadge,
} from '@/components/verso/ui';
import type { DataTableColumn } from '@/components/verso/ui';
import { msg, t } from '@/lib/i18n';
import { index } from '@/routes/admin/seo';
import type { Paginated } from '@/types';

type Props = {
    check: SeoCheckRow;
    items: Paginated<SeoIssueItem>;
    filters: { type: string; search: string };
    types: { value: string; label: string }[];
    shown: number;
    linkCheck: LinkCheckState;
    can: { manage: boolean };
};

// Value of the type select that means every type.
const ALL_TYPES = 'all';

// Every page, image or address that has one issue, with a link to fix each.
export default function SeoIssue({
    check,
    items,
    filters,
    types,
    shown,
    linkCheck,
    can,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const reload = (query: Partial<Props['filters']>) =>
        router.get(
            check.href,
            { ...filters, ...query },
            { preserveState: true, preserveScroll: true },
        );
    const needsLinks = ['broken-links', 'orphan-pages', 'slow-pages'].includes(
        check.key,
    );

    const columns: DataTableColumn<SeoIssueItem>[] = [
        {
            key: 'title',
            header: t('Page or item'),
            primary: true,
            className: 'max-w-0 w-2/5',
            cell: (item) =>
                item.editUrl ? (
                    <Link
                        href={item.editUrl}
                        className="block truncate font-medium text-foreground hover:underline"
                    >
                        {item.title}
                    </Link>
                ) : (
                    <span className="block truncate font-medium text-foreground">
                        {item.title}
                    </span>
                ),
        },
        {
            key: 'type',
            header: t('Type'),
            className: 'whitespace-nowrap text-muted-foreground',
            hideOnMobile: true,
            cell: (item) => item.typeLabel ?? '',
        },
        {
            key: 'detail',
            header: t('What to fix'),
            wrap: true,
            className: 'text-muted-foreground',
            cell: (item) => item.detail,
        },
    ];

    return (
        <>
            <Head title={check.label} />
            <PageHeader
                icon="seo"
                title={check.label}
                description={check.description}
                actions={
                    <div className="flex flex-wrap gap-2">
                        {check.fixUrl && check.fix && (
                            <Button
                                type="button"
                                className="min-h-10 md:min-h-9"
                                onClick={() =>
                                    router.post(
                                        check.fixUrl as string,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {check.fix.label}
                            </Button>
                        )}
                        {check.fix?.kind === 'link' && check.fix.url && (
                            <Button
                                asChild
                                variant="outline"
                                className="min-h-10 md:min-h-9"
                            >
                                <Link href={check.fix.url}>
                                    {check.fix.label}
                                </Link>
                            </Button>
                        )}
                        <Button
                            asChild
                            variant="ghost"
                            className="min-h-10 md:min-h-9"
                        >
                            <Link href={index.url()}>
                                {t('Back to the overview')}
                            </Link>
                        </Button>
                    </div>
                }
            />

            <div className="mb-4 flex flex-wrap items-center gap-3">
                <StatusBadge
                    tone={
                        check.affected > 0
                            ? SEVERITY_TONES[check.severity]
                            : 'success'
                    }
                >
                    {check.affected > 0 ? check.severityLabel : t('Passed')}
                </StatusBadge>
                <span className="text-sm text-muted-foreground">
                    {affectedText(check)}
                </span>
                {check.affected > shown && (
                    <span className="text-sm text-muted-foreground">
                        {t('Showing the first {shown, number}.', { shown })}
                    </span>
                )}
            </div>

            {needsLinks && (
                <div className="mb-4">
                    <LinkCheckCard state={linkCheck} canManage={can.manage} />
                </div>
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
                        placeholder={t('Search this list')}
                        aria-label={t('Search this list')}
                        className="ps-9"
                    />
                </form>
                {types.length > 1 && (
                    <Select
                        value={filters.type || ALL_TYPES}
                        onValueChange={(value) =>
                            reload({ type: value === ALL_TYPES ? '' : value })
                        }
                    >
                        <SelectTrigger
                            className="w-full sm:w-48"
                            aria-label={t('Content type')}
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL_TYPES}>
                                {t('All types')}
                            </SelectItem>
                            {types.map((type) => (
                                <SelectItem key={type.value} value={type.value}>
                                    {type.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                )}
            </div>

            <DataTable
                rows={items.data}
                columns={columns}
                rowKey={(item) => item.key}
                caption={check.label}
                rowLabel={(item) => item.title}
                rowActions={(item) =>
                    item.editUrl
                        ? [
                              {
                                  label: t('Fix it'),
                                  onSelect: () =>
                                      router.visit(item.editUrl as string),
                              },
                          ]
                        : []
                }
                empty={
                    <EmptyState
                        icon="seo"
                        title={
                            check.pending
                                ? check.summary
                                : t('Nothing to fix here.')
                        }
                    />
                }
            />
            <Pagination page={items} />
        </>
    );
}

SeoIssue.layout = {
    breadcrumbs: [{ title: msg('SEO overview'), href: index() }],
};
