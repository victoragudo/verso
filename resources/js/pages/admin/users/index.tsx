import { Head, Link, router, usePage } from '@inertiajs/react';
import { Mail, Search, UserPlus } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Pagination } from '@/components/verso/content/pagination';
import {
    chooserColumns,
    ColumnChooser,
    DataTable,
    EmptyState,
    FilterTabs,
    PageHeader,
    RowActions,
    SectionCard,
    SiteDate,
    StatusBadge,
    useListView,
} from '@/components/verso/ui';
import type {
    DataTableColumn,
    ListViewState,
    RowAction,
} from '@/components/verso/ui';
import { AddUserDialog } from '@/components/verso/users/add-user-dialog';
import { DeleteUserDialog } from '@/components/verso/users/delete-user-dialog';
import { RoleSelect } from '@/components/verso/users/role-select';
import { VersoIcon } from '@/components/verso/icons';
import { useCan } from '@/hooks/use-can';
import { useInitials } from '@/hooks/use-initials';
import { index as rolesIndex } from '@/routes/admin/roles';
import { formatNumber, t } from '@/lib/i18n';
import { index, role as updateRole } from '@/routes/admin/users';
import { edit as editAccess } from '@/routes/admin/users/access';
import { resend as resendInvitation } from '@/routes/admin/users/invitation';
import type { AdminUser, Paginated, RoleOption, UserRole } from '@/types';

type Props = {
    users: Paginated<AdminUser>;
    invitations: AdminUser[];
    filters: { role: string | null; search: string };
    counts: Record<string, number>;
    roleTabs: { value: string; label: string }[];
    can: { create: boolean };
    roles: RoleOption[];
    listView: ListViewState;
    reassignOptions: { id: number; name: string }[];
};

// Lists users with a role filter and search. Admins add, invite, change roles and delete here.
export default function UsersIndex({
    users,
    invitations,
    filters,
    counts,
    roleTabs,
    can,
    roles,
    reassignOptions,
    listView,
}: Props) {
    const view = useListView(listView);
    const canDo = useCan();
    const { errors } = usePage().props as { errors: Record<string, string> };
    const [search, setSearch] = useState(filters.search);
    const [adding, setAdding] = useState<'create' | 'invite' | null>(null);
    const [deleting, setDeleting] = useState<AdminUser | null>(null);

    const tabs = [
        { key: 'all' as const, label: t('All') },
        ...roleTabs.map((tab) => ({ key: tab.value, label: tab.label })),
    ].map((tab) => ({
        key: tab.key,
        label: tab.label,
        count: counts[tab.key] ?? 0,
        href: index.url({
            query: { role: tab.key === 'all' ? undefined : tab.key },
        }),
    }));

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            index.url({ query: { role: filters.role ?? undefined, search } }),
            {},
            { preserveState: true },
        );
    };

    const changeRole = (user: AdminUser, value: UserRole) => {
        router.put(
            updateRole.url(user.id),
            { role: value },
            { preserveScroll: true },
        );
    };

    const resend = (user: AdminUser) => {
        router.post(
            resendInvitation.url(user.id),
            {},
            { preserveScroll: true },
        );
    };

    const rowActions = (user: AdminUser): RowAction[] => [
        {
            label: t('Manage access'),
            href: editAccess.url(user.id),
            hidden: !user.can.promote,
        },
        {
            label: t('Resend invitation'),
            onSelect: () => resend(user),
            hidden: !user.invited,
        },
        {
            label: t('Delete user'),
            onSelect: () => setDeleting(user),
            destructive: true,
            hidden: user.is_self || !user.can.delete,
        },
    ];

    // The role picker when this user may be promoted, else the role name. Extra roles follow as badges.
    const roleCell = (user: AdminUser) => (
        <div className="flex flex-wrap items-center gap-1.5">
            {user.can.promote ? (
                <div className="w-40">
                    <RoleSelect
                        id={`role-${user.id}`}
                        roles={roles}
                        value={user.role}
                        onChange={(value) => changeRole(user, value)}
                    />
                </div>
            ) : (
                <StatusBadge tone="neutral">{user.role_label}</StatusBadge>
            )}
            {user.roles
                .filter((extra) => extra.slug !== user.role)
                .map((extra) => (
                    <span
                        key={extra.slug}
                        className="rounded-md border px-1.5 py-0.5 text-xs whitespace-nowrap text-muted-foreground"
                    >
                        {extra.name}
                    </span>
                ))}
        </div>
    );

    const columns: DataTableColumn<AdminUser>[] = [
        {
            key: 'user',
            header: t('User'),
            primary: true,
            className:
                'w-[45%] max-w-0 @min-[80rem]/table:w-[32%] @min-[125rem]/table:w-[24%]',
            cell: (user) => <UserIdentity user={user} />,
        },
        {
            key: 'role',
            header: t('Role'),
            cell: roleCell,
        },
        {
            key: 'status',
            header: t('Status'),
            cell: () => <StatusBadge tone="success">{t('Active')}</StatusBadge>,
        },
        {
            key: 'entries',
            header: t('Entries'),
            label: t('Entries'),
            optional: true,
            defaultFrom: 'md',
            className: 'text-end',
            cell: (user) => formatNumber(user.entries_count),
        },
        {
            key: 'joined',
            header: t('Joined'),
            label: t('Joined'),
            optional: true,
            defaultFrom: 'lg',
            className: 'whitespace-nowrap text-muted-foreground',
            hideOnMobile: true,
            cell: (user) => <SiteDate iso={user.created_at} />,
        },
        {
            key: 'email',
            header: t('Email'),
            label: t('Email'),
            optional: true,
            defaultFrom: 'xl',
            className: 'whitespace-nowrap',
            cell: (user) => (
                <a
                    href={`mailto:${user.email}`}
                    className="text-brand hover:underline"
                >
                    {user.email}
                </a>
            ),
        },
        {
            key: 'two_factor',
            header: t('Two-factor'),
            label: t('Two-factor sign in'),
            optional: true,
            defaultFrom: '2xl',
            className: 'whitespace-nowrap',
            cell: (user) =>
                user.two_factor ? (
                    <StatusBadge tone="success">{t('On')}</StatusBadge>
                ) : (
                    <StatusBadge tone="neutral">{t('Off')}</StatusBadge>
                ),
        },
        {
            key: 'updated',
            header: t('Last modified'),
            label: t('Last modified'),
            optional: true,
            defaultFrom: '2xl',
            className: 'whitespace-nowrap text-muted-foreground',
            cell: (user) => <SiteDate iso={user.updated_at ?? null} />,
        },
    ];

    return (
        <>
            <Head title={t('Users')} />
            <PageHeader
                icon="users"
                title={t('Users')}
                description={t('People who can sign in to this site.')}
                actions={
                    <>
                        {canDo('manage_roles') && (
                            <Button variant="outline" asChild>
                                <Link href={rolesIndex.url()}>
                                    <VersoIcon name="shield" size={16} />
                                    {t('Roles')}
                                </Link>
                            </Button>
                        )}
                        {can.create && (
                            <>
                                <Button
                                    variant="outline"
                                    onClick={() => setAdding('create')}
                                >
                                    <UserPlus />
                                    {t('Add user')}
                                </Button>
                                <Button onClick={() => setAdding('invite')}>
                                    <Mail />
                                    {t('Invite')}
                                </Button>
                            </>
                        )}
                    </>
                }
            />

            {errors.role && (
                <div
                    role="alert"
                    className="mb-4 rounded-lg bg-danger-soft px-4 py-3 text-sm text-danger-text"
                >
                    {errors.role}
                </div>
            )}

            <div className="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <FilterTabs
                    tabs={tabs}
                    active={filters.role ?? 'all'}
                    label={t('Filter users by role')}
                />
                <div className="flex w-full items-center gap-2 lg:w-auto">
                    <form
                        onSubmit={submitSearch}
                        role="search"
                        className="relative min-w-0 flex-1 lg:w-72 lg:flex-none"
                    >
                        <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={t('Search by name or email')}
                            aria-label={t('Search users')}
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
            </div>

            <DataTable
                rows={users.data}
                columns={columns}
                visibleColumns={view.columns}
                density={view.density}
                rowKey={(user) => user.id}
                caption={t('Users')}
                rowLabel={(user) => user.name}
                rowActions={rowActions}
                empty={
                    <EmptyState
                        icon="users"
                        title={
                            filters.search
                                ? t('No users match “{search}”.', {
                                      search: filters.search,
                                  })
                                : t('No active users here yet.')
                        }
                        action={
                            !filters.search &&
                            can.create && (
                                <Button
                                    variant="outline"
                                    onClick={() => setAdding('invite')}
                                >
                                    <Mail />
                                    {t('Invite someone')}
                                </Button>
                            )
                        }
                    />
                }
            />

            {/* Users > Invitations in the sidebar links here. */}
            <div id="invitations" className="mt-6 scroll-mt-20">
                <SectionCard
                    title={t('Pending invitations')}
                    description={
                        invitations.length > 0
                            ? t('These people have not chosen a password yet.')
                            : t('Nobody is waiting to accept an invitation.')
                    }
                    icon="users"
                    contentClassName="p-0"
                >
                    <ul className="divide-y">
                        {invitations.map((user) => (
                            <li
                                key={user.id}
                                className="flex flex-wrap items-center gap-3 px-4 py-3 sm:px-5"
                            >
                                <div className="min-w-0 flex-1 basis-56">
                                    <UserIdentity user={user} />
                                </div>
                                <div className="flex items-center gap-2">
                                    <StatusBadge tone="warning">
                                        {t('Invited')}
                                    </StatusBadge>
                                    {roleCell(user)}
                                </div>
                                <div className="ms-auto flex items-center gap-1">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="min-h-10 md:min-h-8"
                                        onClick={() => resend(user)}
                                    >
                                        {t('Resend')}
                                    </Button>
                                    <RowActions
                                        label={t('Actions for {name}', {
                                            name: user.name,
                                        })}
                                        actions={rowActions(user).filter(
                                            (action) => action.destructive,
                                        )}
                                    />
                                </div>
                            </li>
                        ))}
                    </ul>
                </SectionCard>
            </div>

            <div className="mt-4">
                <Pagination page={users} />
            </div>

            <AddUserDialog
                mode={adding}
                roles={roles}
                onClose={() => setAdding(null)}
            />
            <DeleteUserDialog
                user={deleting}
                options={reassignOptions}
                onClose={() => setDeleting(null)}
            />
        </>
    );
}

// Avatar with initials, then the name and email of a user.
function UserIdentity({ user }: { user: AdminUser }) {
    const getInitials = useInitials();

    return (
        <div className="flex min-w-0 items-center gap-3">
            <Avatar className="size-9">
                <AvatarFallback className="text-xs font-medium text-muted-foreground">
                    {getInitials(user.name)}
                </AvatarFallback>
            </Avatar>
            <div className="min-w-0">
                <div className="flex min-w-0 items-center gap-2">
                    <span className="truncate font-medium text-foreground">
                        {user.name}
                    </span>
                    {user.is_self && (
                        <StatusBadge tone="neutral">{t('You')}</StatusBadge>
                    )}
                </div>
                <div className="truncate text-sm font-normal text-muted-foreground">
                    {user.email}
                </div>
            </div>
        </div>
    );
}

UsersIndex.layout = () => ({
    breadcrumbs: [{ title: t('Users'), href: index() }],
});
