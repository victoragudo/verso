import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Pagination } from '@/components/verso/content/pagination';
import { EntryStatusBadge } from '@/components/verso/content/status-badge';
import type { ShopProductRow } from '@/components/verso/shop/types';
import {
    chooserColumns,
    ColumnChooser,
    DataTable,
    EmptyState,
    PageHeader,
    SiteDate,
    StatusBadge,
    useListView,
} from '@/components/verso/ui';
import type { DataTableColumn, ListViewState } from '@/components/verso/ui';
import { formatCurrency, formatNumber, t } from '@/lib/i18n';
import type { EntryStatus, Paginated } from '@/types';

type Props = {
    products: Paginated<ShopProductRow>;
    filters: { search: string };
    createUrl: string;
    indexUrl: string;
    currency: string;
    listView: ListViewState;
};

// Prices, SKUs and stock of every product, with links to edit them.
export default function ShopProductsIndex({
    products,
    filters,
    createUrl,
    indexUrl,
    currency,
    listView,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const view = useListView(listView);

    const columns: DataTableColumn<ShopProductRow>[] = [
        {
            key: 'title',
            header: t('shop::Product'),
            primary: true,
            className:
                'w-[45%] max-w-0 @min-[80rem]/table:w-[32%] @min-[125rem]/table:w-[24%]',
            cell: (product) => (
                <span className="flex min-w-0 items-center gap-2">
                    <Link
                        href={product.editUrl}
                        className="truncate font-medium text-foreground hover:text-brand hover:underline"
                    >
                        {product.title}
                    </Link>
                    {product.status !== 'published' && (
                        <EntryStatusBadge
                            status={product.status as EntryStatus}
                        />
                    )}
                </span>
            ),
        },
        {
            key: 'price',
            header: t('shop::Price ({currency})', { currency }),
            className: 'whitespace-nowrap',
            cell: (product) =>
                product.price ? (
                    priceText(product.price, currency)
                ) : (
                    <span className="text-muted-foreground">
                        {t('shop::Not set')}
                    </span>
                ),
        },
        {
            key: 'sku',
            header: t('shop::SKU'),
            label: t('shop::SKU'),
            optional: true,
            defaultFrom: 'md',
            className:
                'whitespace-nowrap font-mono text-xs text-muted-foreground',
            cell: (product) => product.sku ?? '·',
        },
        {
            key: 'stock',
            header: t('shop::Stock'),
            className: 'whitespace-nowrap md:text-end',
            cell: (product) => <StockCell product={product} />,
        },
        {
            key: 'variants',
            header: t('shop::Options'),
            label: t('shop::Options'),
            optional: true,
            defaultFrom: 'lg',
            hideOnMobile: true,
            className: 'whitespace-nowrap text-muted-foreground md:text-end',
            cell: (product) =>
                product.variants ? formatNumber(product.variants) : '·',
        },
        {
            key: 'status',
            header: t('Status'),
            label: t('Status'),
            optional: true,
            defaultFrom: 'xl',
            className: 'whitespace-nowrap',
            cell: (product) => (
                <EntryStatusBadge status={product.status as EntryStatus} />
            ),
        },
        {
            key: 'updated',
            header: t('Last modified'),
            label: t('Last modified'),
            optional: true,
            defaultFrom: '2xl',
            className: 'whitespace-nowrap text-muted-foreground',
            cell: (product) => <SiteDate iso={product.updated_at ?? null} />,
        },
    ];

    return (
        <>
            <Head title={t('shop::Products')} />
            <PageHeader
                icon="shop"
                title={t('shop::Products')}
                description={t(
                    'shop::Prices, SKUs and stock of your products.',
                )}
                actions={
                    <Button asChild className="min-h-10 md:min-h-9">
                        <Link href={createUrl}>
                            <Plus className="size-4" />
                            {t('shop::New product')}
                        </Link>
                    </Button>
                }
            />

            <div className="flex flex-col gap-4">
                <div className="flex w-full items-center gap-2 sm:ms-auto sm:w-auto">
                    <form
                        role="search"
                        onSubmit={(event) => {
                            event.preventDefault();
                            router.get(
                                indexUrl,
                                search !== '' ? { search } : {},
                                {
                                    preserveState: true,
                                },
                            );
                        }}
                        className="relative min-w-0 flex-1 sm:w-72 sm:flex-none"
                    >
                        <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={t('shop::Name or SKU')}
                            aria-label={t('shop::Search products')}
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

                <DataTable
                    rows={products.data}
                    columns={columns}
                    visibleColumns={view.columns}
                    density={view.density}
                    rowKey={(product) => product.id}
                    caption={t('shop::Products')}
                    rowLabel={(product) => product.title}
                    rowActions={(product) => [
                        {
                            label: t('shop::Edit price and stock'),
                            href: product.editUrl,
                        },
                        {
                            label: t('shop::Edit description'),
                            href: product.contentUrl,
                        },
                        {
                            label: t('shop::View in shop'),
                            href: product.viewUrl ?? undefined,
                            external: true,
                            hidden: product.viewUrl === null,
                        },
                    ]}
                    empty={
                        <EmptyState
                            icon="product"
                            title={
                                filters.search !== ''
                                    ? t('shop::No products match your search')
                                    : t('shop::No products yet')
                            }
                            description={t(
                                'shop::Write the product first, then set its price and stock here.',
                            )}
                            action={
                                <Button asChild variant="outline" size="sm">
                                    <Link href={createUrl}>
                                        {t('shop::New product')}
                                    </Link>
                                </Button>
                            }
                        />
                    }
                />

                <Pagination page={products} />
            </div>
        </>
    );
}

function StockCell({ product }: { product: ShopProductRow }) {
    if (!product.tracksStock || product.stock === null) {
        return (
            <span className="text-muted-foreground">
                {t('shop::Not tracked')}
            </span>
        );
    }

    if (product.stock === 0) {
        return <StatusBadge tone="danger">{t('shop::Sold out')}</StatusBadge>;
    }

    return (
        <span className="text-foreground">{formatNumber(product.stock)}</span>
    );
}

function priceText(
    price: NonNullable<ShopProductRow['price']>,
    currency: string,
): string {
    const low = formatCurrency(price.low, currency);

    return price.low === price.high
        ? low
        : t('shop::{low} to {high}', {
              low,
              high: formatCurrency(price.high, currency),
          });
}

ShopProductsIndex.layout = (props: Props) => ({
    breadcrumbs: [{ title: t('shop::Products'), href: props.indexUrl }],
});
