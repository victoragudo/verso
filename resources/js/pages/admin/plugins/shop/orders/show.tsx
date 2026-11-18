import { Head, useForm } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { FormField } from '@/components/verso/shop/form-field';
import type { ShopOrder } from '@/components/verso/shop/types';
import {
    PageHeader,
    SectionCard,
    SiteDate,
    StatusBadge,
} from '@/components/verso/ui';
import { formatCurrency, formatPercent, t, Trans } from '@/lib/i18n';

type Props = {
    order: ShopOrder;
    nextStatuses: { value: string; label: string }[];
    statusUrl: string;
    showUrl: string;
    indexUrl: string;
};

// One order: lines, totals, customer, payment, status history and status changes.
export default function ShopOrderShow({
    order,
    nextStatuses,
    statusUrl,
}: Props) {
    const form = useForm({ status: nextStatuses[0]?.value ?? '', note: '' });
    const { data, setData, errors, processing } = form;
    const money = (amount: number) => formatCurrency(amount, order.currency);
    const placedAt = order.createdAt;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(statusUrl, {
            preserveScroll: true,
            onSuccess: () => form.reset('note'),
        });
    };

    return (
        <>
            <Head title={t('shop::Order {number}', { number: order.number })} />
            <PageHeader
                icon="orders"
                title={
                    <span className="flex flex-wrap items-center gap-2">
                        {t('shop::Order {number}', { number: order.number })}
                        <StatusBadge tone={order.tone}>
                            {order.statusLabel}
                        </StatusBadge>
                    </span>
                }
                description={
                    placedAt ? (
                        <Trans
                            text={t('shop::Placed <date></date>')}
                            components={{
                                date: () => <SiteDate iso={placedAt} />,
                            }}
                        />
                    ) : undefined
                }
                actions={
                    <Button
                        variant="outline"
                        asChild
                        className="min-h-10 md:min-h-9"
                    >
                        <a
                            href={order.customerUrl}
                            target="_blank"
                            rel="noreferrer"
                        >
                            <ExternalLink className="size-4" />
                            {t('shop::Customer view')}
                        </a>
                    </Button>
                }
            />

            <div className="grid items-start gap-4 @3xl/page:grid-cols-[minmax(0,1fr)_clamp(20rem,24cqi,30rem)]">
                <div className="flex min-w-0 flex-col gap-4">
                    <SectionCard
                        title={t('shop::Items')}
                        icon="product"
                        contentClassName="p-0 sm:p-0"
                    >
                        <ul className="divide-y">
                            {order.items.map((item) => (
                                <li
                                    key={item.id}
                                    className="flex items-start justify-between gap-4 px-4 py-3 text-sm sm:px-5"
                                >
                                    <div className="min-w-0">
                                        <p className="font-medium text-foreground">
                                            {item.name}
                                        </p>
                                        <p className="text-muted-foreground">
                                            {[
                                                item.variant,
                                                item.sku &&
                                                    t('shop::SKU {sku}', {
                                                        sku: item.sku,
                                                    }),
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </p>
                                    </div>
                                    <div className="shrink-0 text-end tabular-nums">
                                        <p className="font-medium text-foreground">
                                            {money(item.total)}
                                        </p>
                                        <p className="text-muted-foreground">
                                            {t('shop::{quantity} × {price}', {
                                                quantity: item.quantity,
                                                price: money(item.unitPrice),
                                            })}
                                        </p>
                                    </div>
                                </li>
                            ))}
                        </ul>
                        <dl className="grid gap-1.5 border-t px-4 py-3 text-sm tabular-nums sm:px-5">
                            <TotalRow
                                label={t('shop::Subtotal')}
                                value={money(order.totals.subtotal)}
                            />
                            <TotalRow
                                label={t('shop::Shipping')}
                                value={money(order.totals.shipping)}
                            />
                            <TotalRow
                                label={t('shop::Tax ({rate})', {
                                    rate: formatPercent(
                                        order.totals.taxRate,
                                        2,
                                    ),
                                })}
                                value={money(order.totals.tax)}
                            />
                            <TotalRow
                                label={t('shop::Total')}
                                value={money(order.totals.total)}
                                strong
                            />
                        </dl>
                    </SectionCard>

                    <SectionCard title={t('shop::History')} icon="calendar">
                        <ol className="grid gap-3 text-sm">
                            {order.history.map((change) => (
                                <li key={change.id} className="flex gap-3">
                                    <span
                                        aria-hidden="true"
                                        className="mt-1.5 size-2 shrink-0 rounded-full bg-border"
                                    />
                                    <div className="min-w-0">
                                        <p className="text-foreground">
                                            {change.from
                                                ? t('shop::{from} → {to}', {
                                                      from: change.from,
                                                      to: change.to,
                                                  })
                                                : t(
                                                      'shop::Placed as {status}',
                                                      {
                                                          status: change.to,
                                                      },
                                                  )}
                                        </p>
                                        <p className="text-muted-foreground">
                                            {change.createdAt && (
                                                <SiteDate
                                                    iso={change.createdAt}
                                                />
                                            )}
                                            {change.user && ` · ${change.user}`}
                                        </p>
                                        {change.note && (
                                            <p className="mt-1 text-muted-foreground">
                                                {change.note}
                                            </p>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ol>
                    </SectionCard>
                </div>

                <div className="flex min-w-0 flex-col gap-4">
                    <SectionCard
                        title={t('shop::Change status')}
                        icon="settings"
                    >
                        {nextStatuses.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t(
                                    'shop::This order is {status, select, cancelled {cancelled} refunded {refunded} other {{label}}}. Its status cannot change any more.',
                                    {
                                        status: order.status,
                                        label: order.statusLabel,
                                    },
                                )}
                            </p>
                        ) : (
                            <form onSubmit={submit} className="grid gap-4">
                                <FormField
                                    label={t('shop::New status')}
                                    id="status"
                                    error={errors.status}
                                    hint={t(
                                        'shop::The customer gets an email about the change.',
                                    )}
                                >
                                    <Select
                                        value={data.status}
                                        onValueChange={(value) =>
                                            setData('status', value)
                                        }
                                    >
                                        <SelectTrigger
                                            id="status"
                                            className="w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {nextStatuses.map((status) => (
                                                <SelectItem
                                                    key={status.value}
                                                    value={status.value}
                                                >
                                                    {status.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </FormField>
                                <FormField
                                    label={t('shop::Note (optional)')}
                                    id="note"
                                    error={errors.note}
                                >
                                    <Input
                                        id="note"
                                        value={data.note}
                                        onChange={(event) =>
                                            setData('note', event.target.value)
                                        }
                                        placeholder={t(
                                            'shop::Sent with the morning post',
                                        )}
                                    />
                                </FormField>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="min-h-10 md:min-h-9"
                                >
                                    {t('shop::Update status')}
                                </Button>
                            </form>
                        )}
                    </SectionCard>

                    <SectionCard title={t('shop::Customer')} icon="users">
                        <dl className="grid gap-3 text-sm">
                            <Detail
                                label={t('Name')}
                                value={order.customer.name}
                            />
                            <Detail
                                label={t('Email')}
                                value={
                                    <a
                                        href={`mailto:${order.customer.email}`}
                                        className="text-brand hover:underline"
                                    >
                                        {order.customer.email}
                                    </a>
                                }
                            />
                            {order.customer.phone && (
                                <Detail
                                    label={t('shop::Phone')}
                                    value={order.customer.phone}
                                />
                            )}
                            <Detail
                                label={t('shop::Ship to')}
                                value={
                                    <address className="not-italic">
                                        {order.address.map((line, index) => (
                                            <span key={index} className="block">
                                                {line}
                                            </span>
                                        ))}
                                    </address>
                                }
                            />
                            {order.notes && (
                                <Detail
                                    label={t('shop::Notes')}
                                    value={order.notes}
                                />
                            )}
                        </dl>
                    </SectionCard>

                    <SectionCard title={t('shop::Payment')} icon="api">
                        <dl className="grid gap-3 text-sm">
                            <Detail
                                label={t('shop::Method')}
                                value={order.payment.gateway}
                            />
                            {order.payment.reference && (
                                <Detail
                                    label={t('shop::Reference')}
                                    value={
                                        <span className="font-mono text-xs break-all">
                                            {order.payment.reference}
                                        </span>
                                    }
                                />
                            )}
                            <Detail
                                label={t('shop::Paid')}
                                value={
                                    order.paidAt ? (
                                        <SiteDate iso={order.paidAt} />
                                    ) : (
                                        t('shop::Not yet')
                                    )
                                }
                            />
                            <Detail
                                label={t('shop::Currency')}
                                value={order.currency}
                            />
                        </dl>
                    </SectionCard>
                </div>
            </div>
        </>
    );
}

function TotalRow({
    label,
    value,
    strong = false,
}: {
    label: string;
    value: string;
    strong?: boolean;
}) {
    return (
        <div
            className={
                strong
                    ? 'mt-1 flex justify-between border-t pt-2 font-semibold text-foreground'
                    : 'flex justify-between text-muted-foreground'
            }
        >
            <dt>{label}</dt>
            <dd>{value}</dd>
        </div>
    );
}

function Detail({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="grid gap-0.5">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="min-w-0 text-foreground">{value}</dd>
        </div>
    );
}

ShopOrderShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: t('shop::Orders'), href: props.indexUrl },
        { title: props.order.number, href: props.showUrl },
    ],
});
