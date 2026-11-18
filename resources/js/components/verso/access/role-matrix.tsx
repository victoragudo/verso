import { Link } from '@inertiajs/react';
import { memo, useMemo } from 'react';
import {
    capabilityMap,
    checkState,
    expandCapabilities,
    groupCapabilities,
    impliedByMap,
} from '@/lib/access';
import type { CheckState } from '@/lib/access';
import { formatList, msg, t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { show } from '@/routes/admin/roles';
import type { AccessRole, Capability } from '@/types';
import {
    AccessTable,
    BODY_CELL,
    CapabilityHeader,
    CORNER_CELL,
    FIRST_COLUMN_WIDTH,
    HEAD_CELL,
    HeadingRow,
    TouchCheckbox,
} from './access-table';
import { RoleBadges } from './role-badges';

export const LOCKED_HINT = msg('Administrators always have every capability');

export type MatrixDraft = Record<string, Set<string>>;

type Props = {
    roles: AccessRole[];
    capabilities: Capability[];
    allCapabilities: Capability[];
    grantable: Set<string>;
    draft: MatrixDraft;
    onToggle: (slug: string, keys: string[], on: boolean) => void;
};

const NOT_GRANTABLE_HINT = msg(
    'You cannot give a capability that you do not have yourself',
);

// The roles x capabilities table. Rows are grouped by group and section; columns are roles.
export function RoleMatrix({
    roles,
    capabilities,
    allCapabilities,
    grantable,
    draft,
    onToggle,
}: Props) {
    const groups = useMemo(
        () => groupCapabilities(capabilities),
        [capabilities],
    );
    const byKey = useMemo(
        () => capabilityMap(allCapabilities),
        [allCapabilities],
    );
    const impliedBy = useMemo(
        () => impliedByMap(allCapabilities),
        [allCapabilities],
    );
    const reached = useMemo(() => {
        const result: Record<string, Set<string>> = {};

        for (const role of roles) {
            result[role.slug] = expandCapabilities(draft[role.slug], byKey);
        }

        return result;
    }, [roles, draft, byKey]);
    const columns = roles.length + 1;

    return (
        <AccessTable caption={t('Capabilities of each role')}>
            <thead>
                <tr>
                    <th
                        scope="col"
                        className={cn(CORNER_CELL, FIRST_COLUMN_WIDTH)}
                    >
                        {t('Capability')}
                    </th>
                    {roles.map((role) => (
                        <RoleHeader key={role.slug} role={role} />
                    ))}
                </tr>
            </thead>
            {groups.map((group) => {
                const keys = group.capabilities.map((item) => item.key);
                const editable = keys.filter((key) => grantable.has(key));

                return (
                    <tbody key={group.group}>
                        <HeadingRow
                            title={group.group}
                            columns={columns}
                            level="group"
                        >
                            {roles.map((role) => (
                                <td
                                    key={role.slug}
                                    className={cn(BODY_CELL, 'bg-muted')}
                                >
                                    <TouchCheckbox
                                        label={t(
                                            'All {group} capabilities for {role}',
                                            {
                                                group: group.group,
                                                role: role.name,
                                            },
                                        )}
                                        checked={
                                            role.locked
                                                ? true
                                                : checkState(
                                                      keys,
                                                      draft[role.slug],
                                                  )
                                        }
                                        disabled={
                                            role.locked || editable.length === 0
                                        }
                                        title={
                                            role.locked
                                                ? t(LOCKED_HINT)
                                                : undefined
                                        }
                                        onChange={() =>
                                            onToggle(
                                                role.slug,
                                                editable,
                                                checkState(
                                                    editable,
                                                    draft[role.slug],
                                                ) !== true,
                                            )
                                        }
                                    />
                                </td>
                            ))}
                        </HeadingRow>
                        {group.sections.map((section) => (
                            <MatrixSection
                                key={section.key}
                                title={section.title}
                                capabilities={section.capabilities}
                                roles={roles}
                                columns={columns}
                                grantable={grantable}
                                draft={draft}
                                reached={reached}
                                impliedBy={impliedBy}
                                onToggle={onToggle}
                            />
                        ))}
                    </tbody>
                );
            })}
        </AccessTable>
    );
}

// A role column header: name linked to its page, user count and badges.
function RoleHeader({ role }: { role: AccessRole }) {
    return (
        <th scope="col" className={cn(HEAD_CELL, 'min-w-36 text-center')}>
            <Link
                href={show.url(role.slug)}
                className="inline-flex min-h-10 items-center justify-center rounded-sm text-foreground underline-offset-4 hover:underline focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none md:min-h-8"
            >
                {role.name}
            </Link>
            <span className="block text-xs font-normal text-muted-foreground tabular-nums">
                {t('{count, plural, one {# user} other {# users}}', {
                    count: role.users,
                })}
            </span>
            <span className="mt-1 flex justify-center">
                <RoleBadges role={role} />
            </span>
            {role.locked && (
                <span className="mt-1 block text-xs font-normal text-muted-foreground">
                    {t('Always every capability')}
                </span>
            )}
        </th>
    );
}

type SectionProps = {
    title: string | null;
    capabilities: Capability[];
    roles: AccessRole[];
    columns: number;
    grantable: Set<string>;
    draft: MatrixDraft;
    reached: Record<string, Set<string>>;
    impliedBy: Map<string, Capability[]>;
    onToggle: (slug: string, keys: string[], on: boolean) => void;
};

// A section sub heading and its capability rows.
function MatrixSection({
    title,
    capabilities,
    roles,
    columns,
    grantable,
    draft,
    reached,
    impliedBy,
    onToggle,
}: SectionProps) {
    return (
        <>
            {title && (
                <HeadingRow title={title} columns={columns} level="section" />
            )}
            {capabilities.map((capability) => (
                <CapabilityRow
                    key={capability.key}
                    capability={capability}
                    roles={roles}
                    flags={roles
                        .map((role) =>
                            role.locked || draft[role.slug].has(capability.key)
                                ? 'c'
                                : reached[role.slug].has(capability.key)
                                  ? 'i'
                                  : 'n',
                        )
                        .join('')}
                    editable={grantable.has(capability.key)}
                    includedIn={formatList(
                        (impliedBy.get(capability.key) ?? []).map(
                            (item) => item.label,
                        ),
                    )}
                    onToggle={onToggle}
                />
            ))}
        </>
    );
}

type RowProps = {
    capability: Capability;
    roles: AccessRole[];
    flags: string;
    editable: boolean;
    includedIn: string;
    onToggle: (slug: string, keys: string[], on: boolean) => void;
};

// One capability row. "flags" has one letter per role (c checked, i included by another, n not), so a click only renders its own row.
const CapabilityRow = memo(function CapabilityRow({
    capability,
    roles,
    flags,
    editable,
    includedIn,
    onToggle,
}: RowProps) {
    return (
        <tr className="hover:[&>td]:bg-muted/40">
            <CapabilityHeader
                capability={capability}
                includedIn={includedIn || undefined}
            />
            {roles.map((role, index) => {
                const flag = flags[index];
                const checked: CheckState = flag === 'c';

                return (
                    <td key={role.slug} className={BODY_CELL}>
                        <TouchCheckbox
                            label={t('{capability} for {role}', {
                                capability: capability.label,
                                role: role.name,
                            })}
                            checked={checked}
                            disabled={role.locked || !editable}
                            title={
                                role.locked
                                    ? t(LOCKED_HINT)
                                    : editable
                                      ? undefined
                                      : t(NOT_GRANTABLE_HINT)
                            }
                            onChange={(on) =>
                                onToggle(role.slug, [capability.key], on)
                            }
                        />
                        {flag === 'i' && (
                            <span className="block text-[0.6875rem] leading-tight text-muted-foreground">
                                {t('Included')}
                            </span>
                        )}
                    </td>
                );
            })}
        </tr>
    );
});
