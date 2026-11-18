import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useCallback, useMemo, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { AccessAuditCard } from '@/components/verso/access/access-audit-card';
import { AddRoleDialog } from '@/components/verso/access/add-role-dialog';
import {
    ALL_GROUPS,
    CapabilityFilters,
} from '@/components/verso/access/capability-filters';
import { RoleMatrix } from '@/components/verso/access/role-matrix';
import type { MatrixDraft } from '@/components/verso/access/role-matrix';
import { VersoIcon } from '@/components/verso/icons';
import { EmptyState, PageHeader } from '@/components/verso/ui';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import { createCapabilityFilter, orderedKeys, sameKeys } from '@/lib/access';
import { formatList, t, Trans } from '@/lib/i18n';
import { compare, index, matrix } from '@/routes/admin/roles';
import { index as usersIndex } from '@/routes/admin/users';
import type { AccessAuditEntry, AccessRole, Capability } from '@/types';

type Props = {
    roles: AccessRole[];
    capabilities: Capability[];
    grantable: string[];
    audit: AccessAuditEntry[];
};

// The saved capabilities of every role, as sets.
function draftOf(roles: AccessRole[]): MatrixDraft {
    return Object.fromEntries(
        roles.map((role) => [role.slug, new Set(role.capabilities)]),
    );
}

// Every role in columns and every capability in rows, with local edits saved together.
export default function RolesIndex({
    roles,
    capabilities,
    grantable,
    audit,
}: Props) {
    const [source, setSource] = useState(roles);
    const [draft, setDraft] = useState(() => draftOf(roles));
    const [search, setSearch] = useState('');
    const [group, setGroup] = useState(ALL_GROUPS);
    const [onlyDifferences, setOnlyDifferences] = useState(false);
    const [adding, setAdding] = useState(false);
    const [saving, setSaving] = useState(false);
    const allowVisit = useRef(false);

    if (source !== roles) {
        setSource(roles);
        setDraft(draftOf(roles));
    }

    const grantableSet = useMemo(() => new Set(grantable), [grantable]);
    const filter = useMemo(
        () => createCapabilityFilter(capabilities),
        [capabilities],
    );
    const groups = useMemo(
        () => [...new Set(capabilities.map((item) => item.group))],
        [capabilities],
    );
    const editableRoles = useMemo(
        () => roles.filter((role) => !role.locked),
        [roles],
    );

    const visible = useMemo(
        () =>
            filter(search).filter(
                (capability) =>
                    (group === ALL_GROUPS || capability.group === group) &&
                    (!onlyDifferences ||
                        new Set(
                            editableRoles.map((role) =>
                                draft[role.slug].has(capability.key),
                            ),
                        ).size > 1),
            ),
        [filter, search, group, onlyDifferences, editableRoles, draft],
    );

    const changed = useMemo(
        () =>
            editableRoles.filter(
                (role) =>
                    !sameKeys(new Set(role.capabilities), draft[role.slug]),
            ),
        [editableRoles, draft],
    );

    useUnsavedChanges(changed.length > 0, allowVisit);

    const toggle = useCallback((slug: string, keys: string[], on: boolean) => {
        setDraft((current) => {
            const next = new Set(current[slug]);

            for (const key of keys) {
                if (on) {
                    next.add(key);
                } else {
                    next.delete(key);
                }
            }

            return { ...current, [slug]: next };
        });
    }, []);

    const save = () => {
        allowVisit.current = true;
        setSaving(true);
        router.put(
            matrix.url(),
            {
                roles: Object.fromEntries(
                    changed.map((role) => [
                        role.slug,
                        orderedKeys(draft[role.slug], capabilities),
                    ]),
                ),
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

    const roleNames = Object.fromEntries(
        roles.map((role) => [role.slug, role.name]),
    );

    return (
        <>
            <Head title={t('Roles')} />
            <PageHeader
                icon="users"
                title={t('Roles')}
                description={t(
                    'What each role can do. Tick a box to give a capability to everyone with that role.',
                )}
                actions={
                    <>
                        <Button variant="outline" asChild>
                            <Link href={compare.url()}>
                                <VersoIcon name="shield" size={16} />
                                {t('Compare roles')}
                            </Link>
                        </Button>
                        <Button onClick={() => setAdding(true)}>
                            <Plus />
                            {t('Add role')}
                        </Button>
                    </>
                }
            />

            <div className="grid gap-6">
                <section
                    aria-label={t('Role capabilities')}
                    className="grid min-w-0 gap-4"
                >
                    <CapabilityFilters
                        search={search}
                        onSearch={setSearch}
                        groups={groups}
                        group={group}
                        onGroup={setGroup}
                        onlyDifferences={onlyDifferences}
                        onOnlyDifferences={setOnlyDifferences}
                    />

                    {visible.length === 0 ? (
                        <div className="rounded-lg border bg-card shadow-xs">
                            <EmptyState
                                icon="search"
                                title={t(
                                    'No capability matches these filters.',
                                )}
                            />
                        </div>
                    ) : (
                        <RoleMatrix
                            roles={roles}
                            capabilities={visible}
                            allCapabilities={capabilities}
                            grantable={grantableSet}
                            draft={draft}
                            onToggle={toggle}
                        />
                    )}

                    {changed.length > 0 && (
                        <div className="sticky bottom-4 z-40 flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-card px-4 py-3 shadow-xs">
                            <p role="status" className="text-sm">
                                <Trans
                                    text={t(
                                        '{count, plural, one {# role changed} other {# roles changed}}: <roles>{names}</roles>',
                                        {
                                            count: changed.length,
                                            names: formatList(
                                                changed.map(
                                                    (role) => role.name,
                                                ),
                                            ),
                                        },
                                    )}
                                    components={{
                                        roles: (text) => (
                                            <span className="text-muted-foreground">
                                                {text}
                                            </span>
                                        ),
                                    }}
                                />
                            </p>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    variant="outline"
                                    onClick={() => setDraft(draftOf(roles))}
                                >
                                    {t('Discard')}
                                </Button>
                                <Button onClick={save} disabled={saving}>
                                    {t('Save')}
                                </Button>
                            </div>
                        </div>
                    )}
                </section>

                <AccessAuditCard
                    entries={audit}
                    capabilities={capabilities}
                    roleNames={roleNames}
                />
            </div>

            <AddRoleDialog
                open={adding}
                roles={roles.map((role) => ({
                    slug: role.slug,
                    name: role.name,
                }))}
                onClose={() => setAdding(false)}
            />
        </>
    );
}

RolesIndex.layout = () => ({
    breadcrumbs: [
        { title: t('Users'), href: usersIndex() },
        { title: t('Roles'), href: index() },
    ],
});
