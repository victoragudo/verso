import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    DataTable,
    EmptyState,
    PageHeader,
    SectionCard,
} from '@/components/verso/ui';
import type { DataTableColumn } from '@/components/verso/ui';
import { formatNumber, msg, t } from '@/lib/i18n';

type PlanRow = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    level: number;
    members: number;
};

type Props = {
    plans: PlanRow[];
    settings: { join_url: string; teaser_message: string };
    urls: { store: string; plan: string; settings: string; members: string };
};

type PlanForm = {
    name: string;
    slug: string;
    description: string;
    level: string;
};

const EMPTY_PLAN: PlanForm = {
    name: '',
    slug: '',
    description: '',
    level: '1',
};

// Membership plans with access levels, and the teaser shown to visitors.
export default function PlansIndex({ plans, settings, urls }: Props) {
    const [editing, setEditing] = useState<PlanRow | null>(null);
    const form = useForm<PlanForm>(EMPTY_PLAN);
    const teaser = useForm(settings);
    const planUrl = (id: number) => urls.plan.replace('__ID__', String(id));

    form.transform((values) => ({ ...values, level: Number(values.level) }));

    const edit = (plan: PlanRow) => {
        setEditing(plan);
        form.setData({
            name: plan.name,
            slug: plan.slug,
            description: plan.description ?? '',
            level: String(plan.level),
        });
    };

    const reset = () => {
        setEditing(null);
        form.setData(EMPTY_PLAN);
        form.clearErrors();
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: reset };

        if (editing) {
            form.put(planUrl(editing.id), options);
        } else {
            form.post(urls.store, options);
        }
    };

    const remove = (plan: PlanRow) => {
        if (
            window.confirm(
                t('memberships::Delete the {name} plan?', { name: plan.name }),
            )
        ) {
            router.delete(planUrl(plan.id), { preserveScroll: true });
        }
    };

    const columns: DataTableColumn<PlanRow>[] = [
        {
            key: 'name',
            header: t('memberships::Plan'),
            primary: true,
            cell: (plan) => (
                <div className="min-w-0">
                    <div className="truncate font-medium text-foreground">
                        {plan.name}
                    </div>
                    {plan.description && (
                        <div className="truncate text-xs text-muted-foreground">
                            {plan.description}
                        </div>
                    )}
                </div>
            ),
        },
        {
            key: 'level',
            header: t('memberships::Level'),
            className: 'tabular-nums',
            cell: (plan) => formatNumber(plan.level),
        },
        {
            key: 'members',
            header: t('memberships::Active members'),
            className: 'tabular-nums md:text-end',
            cell: (plan) => formatNumber(plan.members),
        },
    ];

    return (
        <>
            <Head title={t('memberships::Membership plans')} />
            <PageHeader
                icon="memberships"
                title={t('memberships::Membership plans')}
                description={t(
                    'memberships::A plan with a higher level opens everything that lower levels open.',
                )}
                actions={
                    <Button asChild variant="outline">
                        <Link href={urls.members}>
                            {t('memberships::Members')}
                        </Link>
                    </Button>
                }
            />

            <div className="grid items-start gap-4 @3xl/page:grid-cols-[clamp(20rem,22cqi,28rem)_minmax(0,1fr)]">
                <div className="grid gap-4">
                    <SectionCard
                        title={
                            editing
                                ? t('memberships::Edit {name}', {
                                      name: editing.name,
                                  })
                                : t('memberships::New plan')
                        }
                    >
                        <form onSubmit={submit} className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="name">{t('Name')}</Label>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                    placeholder={t('memberships::Supporter')}
                                    required
                                />
                                <InputError message={form.errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="slug">{t('Slug')}</Label>
                                <Input
                                    id="slug"
                                    value={form.data.slug}
                                    onChange={(event) =>
                                        form.setData('slug', event.target.value)
                                    }
                                    placeholder={t(
                                        'memberships::Made from the name',
                                    )}
                                />
                                <InputError message={form.errors.slug} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="level">
                                    {t('memberships::Access level')}
                                </Label>
                                <Input
                                    id="level"
                                    type="number"
                                    inputMode="numeric"
                                    min={1}
                                    className="tabular-nums"
                                    value={form.data.level}
                                    onChange={(event) =>
                                        form.setData(
                                            'level',
                                            event.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError message={form.errors.level} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="description">
                                    {t('Description')}
                                </Label>
                                <Textarea
                                    id="description"
                                    rows={3}
                                    value={form.data.description}
                                    onChange={(event) =>
                                        form.setData(
                                            'description',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={form.errors.description} />
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                    className="min-h-10 flex-1 md:min-h-9"
                                >
                                    {editing
                                        ? t('memberships::Save plan')
                                        : t('memberships::Add plan')}
                                </Button>
                                {editing && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={reset}
                                    >
                                        {t('Cancel')}
                                    </Button>
                                )}
                            </div>
                        </form>
                    </SectionCard>

                    <SectionCard
                        title={t('memberships::Teaser')}
                        description={t(
                            'memberships::Shown to visitors in place of members only content.',
                        )}
                    >
                        <form
                            className="grid gap-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                teaser.put(urls.settings, {
                                    preserveScroll: true,
                                });
                            }}
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="teaser_message">
                                    {t('memberships::Message')}
                                </Label>
                                <Textarea
                                    id="teaser_message"
                                    rows={3}
                                    value={teaser.data.teaser_message}
                                    onChange={(event) =>
                                        teaser.setData(
                                            'teaser_message',
                                            event.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError
                                    message={teaser.errors.teaser_message}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="join_url">
                                    {t('memberships::Join link')}
                                </Label>
                                <Input
                                    id="join_url"
                                    value={teaser.data.join_url}
                                    onChange={(event) =>
                                        teaser.setData(
                                            'join_url',
                                            event.target.value,
                                        )
                                    }
                                    placeholder={t(
                                        'memberships::/join or https://',
                                    )}
                                />
                                <p className="text-xs text-muted-foreground">
                                    {t(
                                        'memberships::Empty hides the “Become a member” button.',
                                    )}
                                </p>
                                <InputError message={teaser.errors.join_url} />
                            </div>
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={teaser.processing}
                            >
                                {t('memberships::Save teaser')}
                            </Button>
                        </form>
                    </SectionCard>
                </div>

                <DataTable
                    rows={plans}
                    columns={columns}
                    rowKey={(plan) => plan.id}
                    caption={t('memberships::Membership plans')}
                    rowLabel={(plan) => plan.name}
                    rowActions={(plan) => [
                        { label: t('Edit'), onSelect: () => edit(plan) },
                        {
                            label: t('Delete'),
                            destructive: true,
                            onSelect: () => remove(plan),
                        },
                    ]}
                    empty={
                        <EmptyState
                            icon="memberships"
                            title={t('memberships::No plans yet.')}
                            description={t(
                                'memberships::Add a plan with the form, like Reader at level 1 and Patron at level 5.',
                            )}
                        />
                    }
                />
            </div>
        </>
    );
}

PlansIndex.layout = {
    breadcrumbs: [
        {
            title: msg('memberships::Membership plans'),
            href: '/admin/plugins/memberships/plans',
        },
    ],
};
