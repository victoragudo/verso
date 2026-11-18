import { Head, Link, router, useForm } from '@inertiajs/react';
import { Search } from 'lucide-react';
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
    DatePicker,
    EmptyState,
    FilterTabs,
    PageHeader,
    SectionCard,
    SiteDate,
    StatusBadge,
    statusTone,
} from '@/components/verso/ui';
import type { DataTableColumn } from '@/components/verso/ui';
import { msg, t } from '@/lib/i18n';
import type { Paginated } from '@/types';

type Status = 'all' | 'active' | 'expired' | 'cancelled';

type MemberRow = {
    id: number;
    user: { id: number; name: string; email: string };
    plan: { id: number; name: string };
    status: 'active' | 'expired' | 'cancelled';
    active: boolean;
    starts_at: string | null;
    expires_at: string | null;
    expires_on: string | null;
    note: string | null;
};

type Props = {
    members: Paginated<MemberRow>;
    filters: { status: Status; search: string };
    counts: Record<Status, number>;
    plans: { id: number; name: string; level: number }[];
    urls: { index: string; store: string; member: string; plans: string };
};

const TABS: { key: Status; label: string }[] = [
    { key: 'all', label: msg('memberships::All') },
    { key: 'active', label: msg('memberships::Active') },
    { key: 'expired', label: msg('memberships::Expired') },
    { key: 'cancelled', label: msg('memberships::Cancelled') },
];

const STATUS_LABELS: Record<MemberRow['status'], string> = {
    active: msg('memberships::Active'),
    expired: msg('memberships::Expired'),
    cancelled: msg('memberships::Cancelled'),
};

// Lists members of every plan, with a form to give a plan to a user by hand.
export default function MembersIndex({
    members,
    filters,
    counts,
    plans,
    urls,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const form = useForm({
        email: '',
        plan_id: plans[0] ? String(plans[0].id) : '',
        expires_on: '',
        note: '',
    });
    const { data, setData, errors, processing } = form;
    const memberUrl = (id: number) => urls.member.replace('__ID__', String(id));
    const query = (status: Status, term = filters.search) =>
        `${urls.index}?${new URLSearchParams({ status, search: term }).toString()}`;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(urls.store, {
            preserveScroll: true,
            onSuccess: () => form.reset('email', 'note', 'expires_on'),
        });
    };

    const setStatus = (member: MemberRow, status: MemberRow['status']) =>
        router.put(
            memberUrl(member.id),
            { status, expires_on: member.expires_on },
            { preserveScroll: true },
        );

    const remove = (member: MemberRow) => {
        if (
            window.confirm(
                t('memberships::Remove the {plan} plan from {name}?', {
                    plan: member.plan.name,
                    name: member.user.name,
                }),
            )
        ) {
            router.delete(memberUrl(member.id), { preserveScroll: true });
        }
    };

    const columns: DataTableColumn<MemberRow>[] = [
        {
            key: 'member',
            header: t('memberships::Member'),
            primary: true,
            cell: (member) => (
                <div className="min-w-0">
                    <div className="truncate font-medium text-foreground">
                        {member.user.name}
                    </div>
                    <div className="truncate text-xs text-muted-foreground">
                        {member.user.email}
                    </div>
                </div>
            ),
        },
        {
            key: 'plan',
            header: t('memberships::Plan'),
            cell: (member) => member.plan.name,
        },
        {
            key: 'status',
            header: t('Status'),
            cell: (member) => {
                const status = member.active
                    ? 'active'
                    : member.status === 'active'
                      ? 'expired'
                      : member.status;

                return (
                    <StatusBadge tone={statusTone(status)}>
                        {t(STATUS_LABELS[status])}
                    </StatusBadge>
                );
            },
        },
        {
            key: 'expires',
            header: t('memberships::Ends'),
            className: 'whitespace-nowrap text-muted-foreground',
            cell: (member) =>
                member.expires_at ? (
                    <SiteDate iso={member.expires_at} dayOnly />
                ) : (
                    t('memberships::Never')
                ),
        },
    ];

    return (
        <>
            <Head title={t('memberships::Members')} />
            <PageHeader
                icon="memberships"
                title={t('memberships::Members')}
                description={t(
                    'memberships::People with a membership plan. Members can read restricted entries and members only blocks.',
                )}
                actions={
                    <Button asChild variant="outline">
                        <Link href={urls.plans}>{t('memberships::Plans')}</Link>
                    </Button>
                }
            />

            <div className="grid items-start gap-4 @3xl/page:grid-cols-[clamp(20rem,22cqi,28rem)_minmax(0,1fr)]">
                <SectionCard
                    title={t('memberships::Add a member')}
                    description={t(
                        'memberships::The user must already have an account.',
                    )}
                >
                    {plans.length === 0 ? (
                        <EmptyState
                            icon="memberships"
                            title={t('memberships::No plans yet.')}
                            action={
                                <Button asChild size="sm">
                                    <Link href={urls.plans}>
                                        {t('memberships::Create a plan')}
                                    </Link>
                                </Button>
                            }
                        />
                    ) : (
                        <form onSubmit={submit} className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    {t('memberships::Email')}
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(event) =>
                                        setData('email', event.target.value)
                                    }
                                    placeholder="reader@example.com"
                                    required
                                    autoComplete="off"
                                    spellCheck={false}
                                />
                                <InputError message={errors.email} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="plan_id">
                                    {t('memberships::Plan')}
                                </Label>
                                <Select
                                    value={data.plan_id}
                                    onValueChange={(value) =>
                                        setData('plan_id', value)
                                    }
                                >
                                    <SelectTrigger
                                        id="plan_id"
                                        className="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {plans.map((plan) => (
                                            <SelectItem
                                                key={plan.id}
                                                value={String(plan.id)}
                                            >
                                                {plan.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.plan_id} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="expires_on">
                                    {t('memberships::Ends on')}
                                </Label>
                                <DatePicker
                                    id="expires_on"
                                    value={data.expires_on}
                                    placeholder={t('memberships::No end date')}
                                    onChange={(value) =>
                                        setData('expires_on', value)
                                    }
                                    aria-describedby="expires_on-hint"
                                />
                                <p
                                    id="expires_on-hint"
                                    className="text-xs text-muted-foreground"
                                >
                                    {t(
                                        'memberships::Leave empty for a membership with no end.',
                                    )}
                                </p>
                                <InputError message={errors.expires_on} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="note">
                                    {t('memberships::Note')}
                                </Label>
                                <Input
                                    id="note"
                                    value={data.note}
                                    onChange={(event) =>
                                        setData('note', event.target.value)
                                    }
                                    placeholder={t(
                                        'memberships::Paid by bank transfer',
                                    )}
                                />
                                <InputError message={errors.note} />
                            </div>
                            <Button
                                type="submit"
                                disabled={processing}
                                className="min-h-10 md:min-h-9"
                            >
                                {t('memberships::Add member')}
                            </Button>
                        </form>
                    )}
                </SectionCard>

                <div className="flex min-w-0 flex-col gap-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <FilterTabs
                            active={filters.status}
                            tabs={TABS.map((tab) => ({
                                ...tab,
                                label: t(tab.label),
                                href: query(tab.key),
                                count: counts[tab.key],
                            }))}
                        />
                        <form
                            role="search"
                            className="relative w-full sm:w-64"
                            onSubmit={(event) => {
                                event.preventDefault();
                                router.get(
                                    query(filters.status, search),
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
                                    'memberships::Search name or email',
                                )}
                                aria-label={t('memberships::Search members')}
                                className="ps-9"
                            />
                        </form>
                    </div>

                    <DataTable
                        rows={members.data}
                        columns={columns}
                        rowKey={(member) => member.id}
                        caption={t('memberships::Members')}
                        rowLabel={(member) => member.user.name}
                        rowActions={(member) => [
                            member.status === 'active'
                                ? {
                                      label: t(
                                          'memberships::Cancel membership',
                                      ),
                                      onSelect: () =>
                                          setStatus(member, 'cancelled'),
                                  }
                                : {
                                      label: t(
                                          'memberships::Make active again',
                                      ),
                                      onSelect: () =>
                                          setStatus(member, 'active'),
                                  },
                            {
                                label: t('memberships::Remove'),
                                destructive: true,
                                onSelect: () => remove(member),
                            },
                        ]}
                        empty={
                            <EmptyState
                                icon="memberships"
                                title={
                                    filters.search
                                        ? t(
                                              'memberships::No members match “{search}”.',
                                              { search: filters.search },
                                          )
                                        : t('memberships::No members here yet.')
                                }
                                description={t(
                                    'memberships::Add a member with the form, or let a shop plugin add them after a payment.',
                                )}
                            />
                        }
                    />
                    <Pagination page={members} />
                </div>
            </div>
        </>
    );
}

MembersIndex.layout = {
    breadcrumbs: [
        {
            title: msg('memberships::Members'),
            href: '/admin/plugins/memberships/members',
        },
    ],
};
