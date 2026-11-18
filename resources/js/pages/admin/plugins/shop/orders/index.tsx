import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import { Input } from '@/components/ui/input';
import { Pagination } from '@/components/verso/content/pagination';
import type { ShopOrderRow } from '@/components/verso/shop/types';
import {
    DataTable,
    EmptyState,
    FilterTabs,
    PageHeader,
    SiteDate,
    StatusBadge,
} from '@/components/verso/ui';
import type { DataTableColumn, FilterTab } from '@/components/verso/ui';
import { formatCurrency, t } from '@/lib/i18n';
import type { Paginated } from '@/types';

type Props = {
    orders: Paginated<ShopOrderRow>;
    filters: { status: string; search: string };
    tabs: FilterTab[];
    indexUrl: string;
};

// Every order with its status, customer and total, filtered by status.
export default function ShopOrdersIndex({
    orders,
    filters,
    tabs,
    indexUrl,
}: Props) {
    const [search, setSearch] = useState(filters.search);

    const columns: DataTableColumn<ShopOrderRow>[] = [
        {
            key: 'number',
            header: t('shop::Order'),
            primary: true,
            cell: (order) => (
                <Link
                    href={order.url}
                    className="font-medium text-foreground hover:text-brand hover:underline"
                >
                    {order.number}
                </Link>
            ),
        },
        {
            key: 'customer',
            header: t('shop::Customer'),
            className: 'max-w-0 w-1/3',
            cell: (order) => (
                <span className="block min-w-0 truncate">
                    {order.customer}
                    <span className="block truncate text-xs text-muted-foreground">
                        {order.email}
                    </span>
                </span>
            ),
        },
        {
            key: 'date',
            header: t('Date'),
            className: 'whitespace-nowrap text-muted-foreground',
            cell: (order) =>
                order.createdAt ? <SiteDate iso={order.createdAt} /> : null,
        },
        {
            key: 'status',
            header: t('Status'),
            cell: (order) => (
                <StatusBadge tone={order.tone}>{order.statusLabel}</StatusBadge>
            ),
        },
        {
            key: 'payment',
            header: t('shop::Payment'),
            hideOnMobile: true,
            className: 'whitespace-nowrap text-muted-foreground',
            cell: (order) => order.payment,
        },
        {
            key: 'total',
            header: t('shop::Total'),
            className: 'whitespace-nowrap md:text-end',
            cell: (order) => (
                <span className="font-medium text-foreground">
                    {formatCurrency(order.total, order.currency)}
                    <span className="block text-xs font-normal text-muted-foreground">
                        {t(
                            'shop::{count, plural, one {# item} other {# items}}',
                            {
                                count: order.items,
                            },
                        )}
                    </span>
                </span>
            ),
        },
    ];

    return (
        <>
            <Head title={t('shop::Orders')} />
            <PageHeader
                icon="orders"
                title={t('shop::Orders')}
                description={t(
                    'shop::Everything customers bought, newest first.',
                )}
            />

            <div className="flex flex-col gap-4">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <FilterTabs
                        tabs={tabs}
                        active={filters.status}
                        label={t('shop::Order status')}
                    />
                    <form
                        role="search"
                        onSubmit={(event) => {
                            event.preventDefault();
                            router.get(
                                indexUrl,
                                {
                                    ...(filters.status !== 'all' && {
                                        status: filters.status,
                                    }),
                                    ...(search !== '' && { search }),
                                },
                                { preserveState: true },
                            );
                        }}
                        className="relative w-full lg:w-72"
                    >
                        <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={t('shop::Order number, name or email')}
                            aria-label={t('shop::Search orders')}
                            className="ps-9"
                        />
                    </form>
                </div>

                <DataTable
                    rows={orders.data}
                    columns={columns}
                    rowKey={(order) => order.id}
                    caption={t('shop::Orders')}
                    rowLabel={(order) =>
                        t('shop::Order {number}', { number: order.number })
                    }
                    rowActions={(order) => [
                        { label: t('shop::Open order'), href: order.url },
                    ]}
                    empty={
                        <EmptyState
                            icon="orders"
                            title={
                                filters.search !== '' ||
                                filters.status !== 'all'
                                    ? t('shop::No orders match this filter')
                                    : t('shop::No orders yet')
                            }
                            description={t(
                                'shop::Orders show up here as soon as customers check out.',
                            )}
                        />
                    }
                />

                <Pagination page={orders} />
            </div>
        </>
    );
}

ShopOrdersIndex.layout = (props: Props) => ({
    breadcrumbs: [{ title: t('shop::Orders'), href: props.indexUrl }],
});
