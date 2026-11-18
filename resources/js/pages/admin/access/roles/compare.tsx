import { Head, router } from '@inertiajs/react';
import { Check, Minus } from 'lucide-react';
import { useId, useMemo, useState } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    AccessTable,
    BODY_CELL,
    CapabilityHeader,
    CORNER_CELL,
    FIRST_COLUMN_WIDTH,
    HEAD_CELL,
    HeadingRow,
} from '@/components/verso/access/access-table';
import { CapabilityFilters } from '@/components/verso/access/capability-filters';
import { RoleBadges } from '@/components/verso/access/role-badges';
import { EmptyState, PageHeader, SectionCard } from '@/components/verso/ui';
import {
    capabilityMap,
    createCapabilityFilter,
    expandCapabilities,
    groupCapabilities,
} from '@/lib/access';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { compare, index } from '@/routes/admin/roles';
import { index as usersIndex } from '@/routes/admin/users';
import type { AccessRole, Capability } from '@/types';

type Props = {
    selected: string[];
    roles: AccessRole[];
    capabilities: Capability[];
    limit: number;
};

// Shows a few roles side by side and where they differ.
export default function RolesCompare({
    selected,
    roles,
    capabilities,
    limit,
}: Props) {
    const [search, setSearch] = useState('');
    const [onlyDifferences, setOnlyDifferences] = useState(false);
    const pickerId = useId();

    const columns = useMemo(
        () =>
            selected
                .map((slug) => roles.find((role) => role.slug === slug))
                .filter((role): role is AccessRole => role !== undefined),
        [selected, roles],
    );
    const reached = useMemo(() => {
        const byKey = capabilityMap(capabilities);

        return Object.fromEntries(
            columns.map((role) => [
                role.slug,
                expandCapabilities(role.capabilities, byKey),
            ]),
        );
    }, [columns, capabilities]);
    const filter = useMemo(
        () => createCapabilityFilter(capabilities),
        [capabilities],
    );

    const differs = (key: string) =>
        new Set(columns.map((role) => reached[role.slug].has(key))).size > 1;

    const groups = groupCapabilities(
        filter(search).filter(
            (capability) => !onlyDifferences || differs(capability.key),
        ),
    );
    const differenceCount = capabilities.filter((capability) =>
        differs(capability.key),
    ).length;

    const pick = (slug: string, on: boolean) => {
        const next = on
            ? [...selected, slug]
            : selected.filter((item) => item !== slug);

        router.get(
            compare.url({ query: { roles: next.join(',') || undefined } }),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Head title={t('Compare roles')} />
            <PageHeader
                icon="users"
                title={t('Compare roles')}
                description={t(
                    'Pick up to {count, plural, one {# role} other {# roles}} to see what each one can do, side by side.',
                    { count: limit },
                )}
            />

            <div className="grid gap-4">
                <SectionCard title={t('Roles to compare')}>
                    <fieldset>
                        <legend className="sr-only">
                            {t('Roles to compare')}
                        </legend>
                        <div className="auto-grid gap-x-4 gap-y-1 [--grid-min:14rem]">
                            {roles.map((role) => {
                                const checked = selected.includes(role.slug);
                                const id = `${pickerId}-${role.slug}`;

                                return (
                                    <div
                                        key={role.slug}
                                        className="flex min-h-10 items-center gap-2"
                                    >
                                        <Checkbox
                                            id={id}
                                            checked={checked}
                                            disabled={
                                                !checked &&
                                                selected.length >= limit
                                            }
                                            onCheckedChange={(value) =>
                                                pick(role.slug, value === true)
                                            }
                                        />
                                        <Label
                                            htmlFor={id}
                                            className="font-normal"
                                        >
                                            {role.name}
                                        </Label>
                                    </div>
                                );
                            })}
                        </div>
                    </fieldset>
                    {selected.length >= limit && (
                        <p className="mt-2 text-xs text-muted-foreground">
                            {t(
                                'You can compare up to {count, plural, one {# role} other {# roles}}. Clear one to pick another.',
                                { count: limit },
                            )}
                        </p>
                    )}
                </SectionCard>

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="min-w-0 flex-1">
                        <CapabilityFilters
                            search={search}
                            onSearch={setSearch}
                            onlyDifferences={onlyDifferences}
                            onOnlyDifferences={setOnlyDifferences}
                        />
                    </div>
                    <p
                        role="status"
                        className="text-sm text-muted-foreground tabular-nums"
                    >
                        {t(
                            '{count, plural, one {# capability differs} other {# capabilities differ}}',
                            { count: differenceCount },
                        )}
                    </p>
                </div>

                {columns.length === 0 || groups.length === 0 ? (
                    <div className="rounded-lg border bg-card shadow-xs">
                        <EmptyState
                            icon="users"
                            title={
                                columns.length === 0
                                    ? t('Pick at least one role to compare.')
                                    : t('No capability matches these filters.')
                            }
                        />
                    </div>
                ) : (
                    <AccessTable
                        caption={t('Capabilities of the roles you picked')}
                    >
                        <thead>
                            <tr>
                                <th
                                    scope="col"
                                    className={cn(
                                        CORNER_CELL,
                                        FIRST_COLUMN_WIDTH,
                                    )}
                                >
                                    {t('Capability')}
                                </th>
                                {columns.map((role) => (
                                    <th
                                        key={role.slug}
                                        scope="col"
                                        className={cn(
                                            HEAD_CELL,
                                            'min-w-32 text-center',
                                        )}
                                    >
                                        <span className="block">
                                            {role.name}
                                        </span>
                                        <span className="mt-1 flex justify-center">
                                            <RoleBadges role={role} />
                                        </span>
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        {groups.map((group) => (
                            <tbody key={group.group}>
                                <HeadingRow
                                    title={group.group}
                                    columns={columns.length + 1}
                                    level="group"
                                />
                                {group.sections.map((section) => [
                                    section.title && (
                                        <HeadingRow
                                            key={section.key}
                                            title={section.title}
                                            columns={columns.length + 1}
                                            level="section"
                                        />
                                    ),
                                    ...section.capabilities.map(
                                        (capability) => {
                                            const different = differs(
                                                capability.key,
                                            );

                                            return (
                                                <tr
                                                    key={capability.key}
                                                    data-differs={
                                                        different || undefined
                                                    }
                                                    className={cn(
                                                        different &&
                                                            '[&>td]:bg-warning-soft/50',
                                                    )}
                                                >
                                                    <CapabilityHeader
                                                        capability={capability}
                                                    />
                                                    {columns.map((role) => (
                                                        <td
                                                            key={role.slug}
                                                            className={cn(
                                                                BODY_CELL,
                                                                'py-2',
                                                            )}
                                                        >
                                                            <Mark
                                                                has={reached[
                                                                    role.slug
                                                                ].has(
                                                                    capability.key,
                                                                )}
                                                            />
                                                        </td>
                                                    ))}
                                                </tr>
                                            );
                                        },
                                    ),
                                ])}
                            </tbody>
                        ))}
                    </AccessTable>
                )}
            </div>
        </>
    );
}

// A check mark or a dash, with "Yes" or "No" for screen readers.
function Mark({ has }: { has: boolean }) {
    return has ? (
        <>
            <Check
                aria-hidden="true"
                className="mx-auto size-4 text-success-text"
            />
            <span className="sr-only">{t('Yes')}</span>
        </>
    ) : (
        <>
            <Minus
                aria-hidden="true"
                className="mx-auto size-4 text-muted-foreground/60"
            />
            <span className="sr-only">{t('No')}</span>
        </>
    );
}

RolesCompare.layout = () => ({
    breadcrumbs: [
        { title: t('Users'), href: usersIndex() },
        { title: t('Roles'), href: index() },
        { title: t('Compare'), href: compare() },
    ],
});
