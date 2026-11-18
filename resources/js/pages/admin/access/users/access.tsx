import { Head, router, usePage } from '@inertiajs/react';
import { useCallback, useId, useMemo, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { AccessAuditCard } from '@/components/verso/access/access-audit-card';
import { CapabilityFilters } from '@/components/verso/access/capability-filters';
import { OverrideControl } from '@/components/verso/access/override-control';
import type { OverrideState } from '@/components/verso/access/override-control';
import {
    EmptyState,
    NoticeBanner,
    PageHeader,
    SectionCard,
} from '@/components/verso/ui';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import {
    createCapabilityFilter,
    effectiveCapabilities,
    groupCapabilities,
    orderedKeys,
    sameKeys,
} from '@/lib/access';
import { t } from '@/lib/i18n';
import { index as usersIndex } from '@/routes/admin/users';
import { edit, update } from '@/routes/admin/users/access';
import type {
    AccessAuditEntry,
    AccessRole,
    AccessSubject,
    Capability,
} from '@/types';

type Props = {
    user: AccessSubject;
    mainRoles: string[];
    roles: AccessRole[];
    capabilities: Capability[];
    grantable: string[];
    audit: AccessAuditEntry[];
};

type Overrides = Record<string, OverrideState>;

const ERROR_FIELDS =
    /^(role|roles|extra_roles|grants|denies|capabilities)(\.|$)/;

function overridesOf(saved: Record<string, boolean>): Overrides {
    return Object.fromEntries(
        Object.entries(saved).map(([key, granted]) => [
            key,
            granted ? 'grant' : 'deny',
        ]),
    );
}

function sameOverrides(a: Overrides, b: Overrides): boolean {
    const keys = Object.keys(a);

    return (
        keys.length === Object.keys(b).length &&
        keys.every((key) => a[key] === b[key])
    );
}

function keysWith(overrides: Overrides, state: OverrideState): string[] {
    return Object.keys(overrides).filter((key) => overrides[key] === state);
}

// The access of one user: main role, extra roles and single grants or denies, with a live preview.
export default function UserAccess({
    user,
    mainRoles,
    roles,
    capabilities,
    grantable,
    audit,
}: Props) {
    const errors = (usePage().props.errors ?? {}) as Record<string, string>;
    const [source, setSource] = useState(user);
    const [role, setRole] = useState(user.role);
    const [extraRoles, setExtraRoles] = useState(user.extraRoles);
    const [overrides, setOverrides] = useState(() =>
        overridesOf(user.overrides),
    );
    const [search, setSearch] = useState('');
    const [saving, setSaving] = useState(false);
    const allowVisit = useRef(false);
    const extraId = useId();

    if (source !== user) {
        setSource(user);
        setRole(user.role);
        setExtraRoles(user.extraRoles);
        setOverrides(overridesOf(user.overrides));
    }

    const saved = useMemo(() => overridesOf(user.overrides), [user.overrides]);
    const grantableSet = useMemo(() => new Set(grantable), [grantable]);
    const hadSet = useMemo(() => new Set(user.effective), [user.effective]);
    const roleBySlug = useMemo(
        () => new Map(roles.map((item) => [item.slug, item])),
        [roles],
    );
    const filter = useMemo(
        () => createCapabilityFilter(capabilities),
        [capabilities],
    );
    const groups = useMemo(
        () => groupCapabilities(filter(search)),
        [filter, search],
    );

    const grants = keysWith(overrides, 'grant');
    const denies = keysWith(overrides, 'deny');
    const slugs = [role, ...extraRoles.filter((slug) => slug !== role)];
    const effective = effectiveCapabilities({
        capabilities,
        roles,
        slugs,
        grants,
        denies,
    });
    const isAdmin = slugs.some((slug) => roleBySlug.get(slug)?.locked);

    const dirty =
        role !== user.role ||
        !sameKeys(new Set(extraRoles), new Set(user.extraRoles)) ||
        !sameOverrides(overrides, saved);

    useUnsavedChanges(dirty, allowVisit);

    const changeOverride = useCallback((key: string, value: OverrideState) => {
        setOverrides((current) => {
            const next = { ...current };

            if (value === 'inherit') {
                delete next[key];
            } else {
                next[key] = value;
            }

            return next;
        });
    }, []);

    const changeMainRole = (slug: string) => {
        setRole(slug);
        setExtraRoles((current) => current.filter((item) => item !== slug));
    };

    const toggleExtra = (slug: string, on: boolean) => {
        setExtraRoles((current) =>
            on ? [...current, slug] : current.filter((item) => item !== slug),
        );
    };

    const save = () => {
        allowVisit.current = true;
        setSaving(true);
        router.put(
            update.url(user.id),
            {
                role,
                extra_roles: extraRoles.filter((slug) => slug !== role),
                grants: orderedKeys(new Set(grants), capabilities),
                denies: orderedKeys(new Set(denies), capabilities),
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    allowVisit.current = false;
                    setSaving(false);
                },
            },
        );
    };

    const discard = () => {
        setRole(user.role);
        setExtraRoles(user.extraRoles);
        setOverrides(saved);
    };

    const errorMessages = Object.entries(errors)
        .filter(([key]) => ERROR_FIELDS.test(key))
        .map(([, message]) => message);
    const roleName = (slug: string) => roleBySlug.get(slug)?.name ?? slug;

    return (
        <>
            <Head title={t('Access of {name}', { name: user.name })} />
            <PageHeader
                icon="shield"
                title={t('Access of {name}', { name: user.name })}
                description={t(
                    '{email}. Roles give most capabilities; use grants and denies only for exceptions.',
                    { email: user.email },
                )}
                actions={
                    <Button onClick={save} disabled={!dirty || saving}>
                        {t('Save')}
                    </Button>
                }
            />

            {errorMessages.length > 0 && (
                <div
                    role="alert"
                    className="mb-4 rounded-lg bg-danger-soft px-4 py-3 text-sm text-danger-text"
                >
                    <ul className="grid gap-1">
                        {errorMessages.map((message) => (
                            <li key={message}>{message}</li>
                        ))}
                    </ul>
                </div>
            )}

            {isAdmin && (
                <NoticeBanner
                    className="mb-4"
                    tone="warning"
                    icon="warning"
                    title={t('Administrators always have every capability')}
                    description={t(
                        'Denies do not apply to administrators, so they cannot lock the site out. Grants and denies take effect only without the Administrator role.',
                    )}
                />
            )}

            <div className="grid items-start gap-6 @6xl/page:grid-cols-[minmax(0,1fr)_minmax(20rem,24rem)] @[120rem]/page:grid-cols-[minmax(0,1fr)_minmax(24rem,30rem)]">
                <div className="grid min-w-0 gap-4">
                    <SectionCard
                        title={t('Roles')}
                        description={t(
                            'The main role, plus extra roles that add their capabilities.',
                        )}
                    >
                        <div className="grid gap-4 @3xl/page:grid-cols-[minmax(0,18rem)_minmax(0,1fr)]">
                            <div className="grid content-start gap-2">
                                <Label htmlFor="main-role">
                                    {t('Main role')}
                                </Label>
                                <Select
                                    value={role}
                                    onValueChange={changeMainRole}
                                >
                                    <SelectTrigger
                                        id="main-role"
                                        aria-invalid={Boolean(errors.role)}
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {mainRoles.map((slug) => (
                                            <SelectItem
                                                key={slug}
                                                value={slug}
                                                disabled={
                                                    slug !== user.role &&
                                                    roleBySlug.get(slug)
                                                        ?.canAssign === false
                                                }
                                            >
                                                {roleName(slug)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <fieldset className="grid content-start gap-1">
                                <legend className="mb-1 text-sm font-medium">
                                    {t('Extra roles')}
                                </legend>
                                <div className="auto-grid gap-x-4 [--grid-min:12rem]">
                                    {roles
                                        .filter((item) => item.slug !== role)
                                        .map((item) => {
                                            const id = `${extraId}-${item.slug}`;
                                            const checked = extraRoles.includes(
                                                item.slug,
                                            );

                                            return (
                                                <div
                                                    key={item.slug}
                                                    className="flex min-h-10 items-center gap-2"
                                                >
                                                    <Checkbox
                                                        id={id}
                                                        checked={checked}
                                                        disabled={
                                                            !item.canAssign &&
                                                            !checked
                                                        }
                                                        onCheckedChange={(
                                                            value,
                                                        ) =>
                                                            toggleExtra(
                                                                item.slug,
                                                                value === true,
                                                            )
                                                        }
                                                    />
                                                    <Label
                                                        htmlFor={id}
                                                        className="font-normal"
                                                    >
                                                        {item.name}
                                                    </Label>
                                                </div>
                                            );
                                        })}
                                </div>
                            </fieldset>
                        </div>
                    </SectionCard>

                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="min-w-0 flex-1">
                            <CapabilityFilters
                                search={search}
                                onSearch={setSearch}
                            />
                        </div>
                        <p
                            role="status"
                            className="text-sm text-muted-foreground tabular-nums"
                        >
                            {t(
                                '{name} can use {count, number} of {total, plural, one {# capability} other {# capabilities}}',
                                {
                                    name: user.name,
                                    count: effective.size,
                                    total: capabilities.length,
                                },
                            )}
                        </p>
                    </div>

                    {groups.length === 0 ? (
                        <div className="rounded-lg border bg-card shadow-xs">
                            <EmptyState
                                icon="search"
                                title={t('No capability matches your search.')}
                            />
                        </div>
                    ) : (
                        <div className="masonry-grid [--grid-min:36rem]">
                            {groups.map((group) => (
                                <SectionCard
                                    key={group.group}
                                    title={group.group}
                                    contentClassName="p-0"
                                >
                                    {group.sections.map((section) => (
                                        <div
                                            key={section.key}
                                            className="border-b last:border-b-0"
                                        >
                                            {section.title && (
                                                <h3 className="bg-muted/50 px-4 py-1.5 text-xs font-semibold text-muted-foreground sm:px-5">
                                                    {section.title}
                                                </h3>
                                            )}
                                            <ul className="divide-y">
                                                {section.capabilities.map(
                                                    (capability) => (
                                                        <OverrideControl
                                                            key={capability.key}
                                                            capability={
                                                                capability
                                                            }
                                                            value={
                                                                overrides[
                                                                    capability
                                                                        .key
                                                                ] ?? 'inherit'
                                                            }
                                                            saved={
                                                                saved[
                                                                    capability
                                                                        .key
                                                                ] ?? 'inherit'
                                                            }
                                                            grantable={grantableSet.has(
                                                                capability.key,
                                                            )}
                                                            allowed={effective.has(
                                                                capability.key,
                                                            )}
                                                            hadIt={hadSet.has(
                                                                capability.key,
                                                            )}
                                                            onChange={
                                                                changeOverride
                                                            }
                                                        />
                                                    ),
                                                )}
                                            </ul>
                                        </div>
                                    ))}
                                </SectionCard>
                            ))}
                        </div>
                    )}

                    {dirty && (
                        <div className="sticky bottom-4 z-10 flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-card px-4 py-3 shadow-xs">
                            <p className="text-sm">
                                {t(
                                    'The access of {name} has unsaved changes.',
                                    {
                                        name: user.name,
                                    },
                                )}
                            </p>
                            <div className="flex flex-wrap gap-2">
                                <Button variant="outline" onClick={discard}>
                                    {t('Discard')}
                                </Button>
                                <Button onClick={save} disabled={saving}>
                                    {t('Save')}
                                </Button>
                            </div>
                        </div>
                    )}
                </div>

                <aside className="grid min-w-0 gap-4">
                    <AccessAuditCard
                        entries={audit}
                        capabilities={capabilities}
                        description={t(
                            'Changes to the access of this user, newest first.',
                        )}
                    />
                </aside>
            </div>
        </>
    );
}

UserAccess.layout = (props: { user: AccessSubject }) => ({
    breadcrumbs: [
        { title: t('Users'), href: usersIndex() },
        { title: props.user.name, href: edit(props.user.id) },
    ],
});
