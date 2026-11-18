import { Head, Link, router, usePage } from '@inertiajs/react';
import { useCallback, useMemo, useRef, useState } from 'react';
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
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { AccessAuditCard } from '@/components/verso/access/access-audit-card';
import { CapabilityChecklist } from '@/components/verso/access/capability-checklist';
import { CapabilityFilters } from '@/components/verso/access/capability-filters';
import { ConfirmDialog } from '@/components/verso/access/confirm-dialog';
import { RoleBadges } from '@/components/verso/access/role-badges';
import {
    EmptyState,
    NoticeBanner,
    PageHeader,
    SectionCard,
    StatusBadge,
} from '@/components/verso/ui';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import {
    createCapabilityFilter,
    groupCapabilities,
    orderedKeys,
    sameKeys,
    toggleAll,
} from '@/lib/access';
import { t } from '@/lib/i18n';
import {
    clone,
    compare,
    destroy,
    index,
    reset,
    show,
    update,
    viewAs,
} from '@/routes/admin/roles';
import { index as usersIndex } from '@/routes/admin/users';
import { edit as editAccess } from '@/routes/admin/users/access';
import type {
    AccessAuditEntry,
    AccessRole,
    Capability,
    RoleMembers,
    RoleSummary,
} from '@/types';

type Props = {
    role: AccessRole;
    defaults: string[] | null;
    capabilities: Capability[];
    grantable: string[];
    roles: RoleSummary[];
    members: RoleMembers;
    audit: AccessAuditEntry[];
    canViewAs: boolean;
};

type Confirming = 'reset' | 'delete' | null;

// One role: its name, description, capabilities, members and history.
export default function RoleShow({
    role,
    defaults,
    capabilities,
    grantable,
    roles,
    members,
    audit,
    canViewAs,
}: Props) {
    const errors = (usePage().props.errors ?? {}) as Record<string, string>;
    const [source, setSource] = useState(role);
    const [name, setName] = useState(role.name);
    const [description, setDescription] = useState(role.description);
    const [selected, setSelected] = useState(() => new Set(role.capabilities));
    const [search, setSearch] = useState('');
    const [confirming, setConfirming] = useState<Confirming>(null);
    const [cloning, setCloning] = useState(false);
    const [saving, setSaving] = useState(false);
    const allowVisit = useRef(false);

    if (source !== role) {
        setSource(role);
        setName(role.name);
        setDescription(role.description);
        setSelected(new Set(role.capabilities));
    }

    const grantableSet = useMemo(() => new Set(grantable), [grantable]);
    const defaultSet = useMemo(
        () => (role.builtIn && defaults ? new Set(defaults) : null),
        [role.builtIn, defaults],
    );
    const filter = useMemo(
        () => createCapabilityFilter(capabilities),
        [capabilities],
    );
    const groups = useMemo(
        () => groupCapabilities(filter(search)),
        [filter, search],
    );
    const dirty =
        !role.locked &&
        (name !== role.name ||
            description !== role.description ||
            !sameKeys(selected, new Set(role.capabilities)));

    useUnsavedChanges(dirty, allowVisit);

    const toggle = useCallback((keys: string[], on: boolean) => {
        setSelected((current) => toggleAll(current, keys, on));
    }, []);

    // Runs a server action without the "leave this page?" question.
    const send = (action: () => void) => {
        allowVisit.current = true;
        action();
    };
    const finish = {
        onStart: () => setSaving(true),
        onFinish: () => {
            allowVisit.current = false;
            setSaving(false);
        },
    };

    const save = (event?: FormEvent) => {
        event?.preventDefault();
        send(() =>
            router.put(
                update.url(role.slug),
                {
                    name,
                    description: description || null,
                    capabilities: orderedKeys(selected, capabilities),
                },
                { preserveScroll: true, ...finish },
            ),
        );
    };

    const discard = () => {
        setName(role.name);
        setDescription(role.description);
        setSelected(new Set(role.capabilities));
    };

    const added = defaultSet
        ? [...selected].filter((key) => !defaultSet.has(key)).length
        : 0;
    const removed = defaultSet
        ? [...defaultSet].filter((key) => !selected.has(key)).length
        : 0;
    const compareWith =
        role.basedOn ?? roles.find((other) => other.slug !== role.slug)?.slug;

    return (
        <>
            <Head title={role.name} />
            <PageHeader
                icon="users"
                title={
                    <span className="inline-flex flex-wrap items-center gap-2">
                        {role.name}
                        <RoleBadges role={role} />
                    </span>
                }
                description={role.description || undefined}
                actions={
                    <>
                        <Button variant="outline" asChild>
                            <Link
                                href={compare.url({
                                    query: {
                                        roles: [role.slug, compareWith]
                                            .filter(Boolean)
                                            .join(','),
                                    },
                                })}
                            >
                                {t('Compare')}
                            </Link>
                        </Button>
                        {canViewAs && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    send(() =>
                                        router.post(
                                            viewAs.url(role.slug),
                                            {},
                                            finish,
                                        ),
                                    )
                                }
                            >
                                {t('View the admin as this role')}
                            </Button>
                        )}
                        <Button
                            variant="outline"
                            onClick={() => setCloning(true)}
                        >
                            {t('Clone')}
                        </Button>
                        {role.builtIn && role.modified && (
                            <Button
                                variant="outline"
                                onClick={() => setConfirming('reset')}
                            >
                                {t('Reset to defaults')}
                            </Button>
                        )}
                        {!role.builtIn && (
                            <Button
                                variant="outline"
                                onClick={() => setConfirming('delete')}
                            >
                                {t('Delete')}
                            </Button>
                        )}
                        {!role.locked && (
                            <Button onClick={() => save()} disabled={!dirty}>
                                {t('Save')}
                            </Button>
                        )}
                    </>
                }
            />

            {role.locked && (
                <NoticeBanner
                    className="mb-4"
                    tone="info"
                    icon="shield"
                    title={t('Administrators always have every capability')}
                    description={t(
                        'This role cannot be changed, so nobody can lock the site out.',
                    )}
                />
            )}

            <div className="grid items-start gap-6 @6xl/page:grid-cols-[minmax(0,1fr)_minmax(20rem,24rem)] @[120rem]/page:grid-cols-[minmax(0,1fr)_minmax(24rem,30rem)]">
                <form onSubmit={save} className="grid min-w-0 gap-4">
                    {!role.locked && (
                        <SectionCard title={t('Details')}>
                            <div className="grid gap-4 @3xl/page:grid-cols-[minmax(0,20rem)_minmax(0,1fr)]">
                                <div className="grid content-start gap-2">
                                    <Label htmlFor="role-name">
                                        {t('Name')}
                                    </Label>
                                    <Input
                                        id="role-name"
                                        value={name}
                                        required
                                        maxLength={120}
                                        onChange={(event) =>
                                            setName(event.target.value)
                                        }
                                        aria-invalid={Boolean(errors.name)}
                                    />
                                    <InputError message={errors.name} />
                                </div>
                                <div className="grid content-start gap-2">
                                    <Label htmlFor="role-description">
                                        {t('Description')}
                                    </Label>
                                    <Textarea
                                        id="role-description"
                                        value={description}
                                        maxLength={500}
                                        rows={2}
                                        onChange={(event) =>
                                            setDescription(event.target.value)
                                        }
                                    />
                                    <InputError message={errors.description} />
                                </div>
                            </div>
                        </SectionCard>
                    )}

                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="min-w-0 flex-1">
                            <CapabilityFilters
                                search={search}
                                onSearch={setSearch}
                            />
                        </div>
                        <p className="text-sm text-muted-foreground tabular-nums">
                            {defaultSet && (added > 0 || removed > 0)
                                ? t(
                                      '{selected, number} of {total, plural, one {# capability} other {# capabilities}}, {added, number} added and {removed, number} removed compared with the defaults',
                                      {
                                          selected: selected.size,
                                          total: capabilities.length,
                                          added,
                                          removed,
                                      },
                                  )
                                : t(
                                      '{selected, number} of {total, plural, one {# capability} other {# capabilities}}',
                                      {
                                          selected: selected.size,
                                          total: capabilities.length,
                                      },
                                  )}
                        </p>
                    </div>

                    <InputError message={errors.capabilities} />

                    {groups.length === 0 ? (
                        <div className="rounded-lg border bg-card shadow-xs">
                            <EmptyState
                                icon="search"
                                title={t('No capability matches your search.')}
                            />
                        </div>
                    ) : (
                        <CapabilityChecklist
                            groups={groups}
                            selected={selected}
                            grantable={grantableSet}
                            readOnly={role.locked}
                            defaults={defaultSet}
                            onToggle={toggle}
                        />
                    )}

                    {dirty && (
                        <div className="sticky bottom-4 z-10 flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-card px-4 py-3 shadow-xs">
                            <p role="status" className="text-sm">
                                {t('This role has unsaved changes.')}
                            </p>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={discard}
                                >
                                    {t('Discard')}
                                </Button>
                                <Button type="submit" disabled={saving}>
                                    {saving && <Spinner aria-hidden="true" />}
                                    {t('Save')}
                                </Button>
                            </div>
                        </div>
                    )}
                </form>

                <aside className="grid min-w-0 gap-4">
                    <MembersCard members={members} />
                    <AccessAuditCard
                        entries={audit}
                        capabilities={capabilities}
                        description={t('Changes to this role, newest first.')}
                    />
                </aside>
            </div>

            <CloneDialog
                open={cloning}
                role={role}
                onClose={() => setCloning(false)}
            />
            <ConfirmDialog
                open={confirming === 'reset'}
                title={t('Reset {role} to its defaults?', { role: role.name })}
                description={t(
                    'The capabilities go back to the ones Verso and your plugins give this role. Everyone with this role gets them at once.',
                )}
                confirmLabel={t('Reset to defaults')}
                onConfirm={() => {
                    setConfirming(null);
                    send(() =>
                        router.post(
                            reset.url(role.slug),
                            {},
                            { preserveScroll: true, ...finish },
                        ),
                    );
                }}
                onClose={() => setConfirming(null)}
            />
            <ConfirmDialog
                open={confirming === 'delete'}
                destructive
                title={t('Delete {role}?', { role: role.name })}
                description={
                    role.users > 0
                        ? t(
                              '{count, plural, one {# person has} other {# people have}} this role and will lose it. This cannot be undone.',
                              { count: role.users },
                          )
                        : t('Nobody has this role. This cannot be undone.')
                }
                confirmLabel={t('Delete role')}
                onConfirm={() => {
                    setConfirming(null);
                    send(() => router.delete(destroy.url(role.slug), finish));
                }}
                onClose={() => setConfirming(null)}
            />
        </>
    );
}

// People with this role, as main or extra role.
function MembersCard({ members }: { members: RoleMembers }) {
    const more = members.total - members.items.length;

    return (
        <SectionCard
            icon="users"
            title={t('Members')}
            description={t(
                '{count, plural, one {# person has this role} other {# people have this role}}',
                { count: members.total },
            )}
            contentClassName="p-0"
        >
            {members.items.length === 0 ? (
                <p className="px-4 py-3 text-sm text-muted-foreground sm:px-5">
                    {t('Nobody has this role yet.')}
                </p>
            ) : (
                <ul className="divide-y">
                    {members.items.map((member) => (
                        <li
                            key={member.id}
                            className="flex items-center justify-between gap-3 px-4 py-2 sm:px-5"
                        >
                            <span className="min-w-0">
                                <Link
                                    href={editAccess.url(member.id)}
                                    className="block truncate text-sm font-medium text-foreground underline-offset-4 hover:underline"
                                >
                                    {member.name}
                                </Link>
                                <span className="block truncate text-xs text-muted-foreground">
                                    {member.email}
                                </span>
                            </span>
                            {member.main && (
                                <StatusBadge tone="neutral">
                                    {t('Main role')}
                                </StatusBadge>
                            )}
                        </li>
                    ))}
                </ul>
            )}
            {more > 0 && (
                <p className="border-t px-4 py-2 text-xs text-muted-foreground sm:px-5">
                    {t(
                        'And {count, plural, one {# more person} other {# more people}}.',
                        { count: more },
                    )}
                </p>
            )}
        </SectionCard>
    );
}

// Asks a name for the copy, then clones the role.
function CloneDialog({
    open,
    role,
    onClose,
}: {
    open: boolean;
    role: AccessRole;
    onClose: () => void;
}) {
    const errors = (usePage().props.errors ?? {}) as Record<string, string>;
    const [name, setName] = useState(() =>
        t('{role} copy', { role: role.name }),
    );

    const [cloningRole, setCloningRole] = useState(false);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.post(
            clone.url(role.slug),
            { name },
            {
                onSuccess: onClose,
                onStart: () => setCloningRole(true),
                onFinish: () => setCloningRole(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {t('Clone {role}', { role: role.name })}
                    </DialogTitle>
                    <DialogDescription>
                        {t(
                            'The new role starts with the same capabilities. Nobody has it yet.',
                        )}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="clone-role-name">
                            {t('Name of the new role')}
                        </Label>
                        <Input
                            id="clone-role-name"
                            value={name}
                            required
                            maxLength={120}
                            onChange={(event) => setName(event.target.value)}
                        />
                        <InputError message={errors.name} />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button
                            type="submit"
                            disabled={!name.trim() || cloningRole}
                        >
                            {cloningRole && <Spinner aria-hidden="true" />}
                            {t('Clone role')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

RoleShow.layout = (props: { role: AccessRole }) => ({
    breadcrumbs: [
        { title: t('Users'), href: usersIndex() },
        { title: t('Roles'), href: index() },
        { title: props.role.name, href: show(props.role.slug) },
    ],
});
