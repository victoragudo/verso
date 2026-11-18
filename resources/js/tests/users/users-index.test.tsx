import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import UsersIndex from '@/pages/admin/users/index';
import { resetPageProps, setPageProps } from '@/tests/mocks/inertia';
import type { AdminUser, Paginated } from '@/types';

// The add and delete dialogs only need a quiet useForm here.
vi.mock('@inertiajs/react', async () => ({
    ...(await import('@/tests/mocks/inertia')).inertiaMock,
    useForm: <T extends Record<string, unknown>>(data: T) => ({
        data,
        setData: vi.fn(),
        transform: vi.fn(),
        errors: {},
        processing: false,
        clearErrors: vi.fn(),
        reset: vi.fn(),
        post: vi.fn(),
        delete: vi.fn(),
    }),
}));

afterEach(() => {
    resetPageProps();
    vi.clearAllMocks();
});

function person(overrides: Partial<AdminUser>): AdminUser {
    return {
        id: 9,
        name: 'Tomás Aguilar',
        email: 'tomas@harborlane.pt',
        role: 'editor',
        role_label: 'Editor',
        roles: [{ slug: 'editor', name: 'Editor' }],
        can: { promote: true, delete: true },
        invited: false,
        entries_count: 12,
        created_at: '2026-03-02T10:00:00+00:00',
        is_self: false,
        ...overrides,
    };
}

const tomas = person({
    roles: [
        { slug: 'editor', name: 'Editor' },
        { slug: 'seo_manager', name: 'SEO manager' },
    ],
});
const ines = person({
    id: 12,
    name: 'Inês Carvalho',
    email: 'ines@harborlane.pt',
    role: 'admin',
    role_label: 'Administrator',
    roles: [{ slug: 'admin', name: 'Administrator' }],
    can: { promote: false, delete: false },
});

function page(data: AdminUser[]): Paginated<AdminUser> {
    return {
        data,
        current_page: 1,
        last_page: 1,
        per_page: 25,
        total: data.length,
        from: 1,
        to: data.length,
        links: [],
    };
}

function renderUsers({
    create = true,
    can = {},
}: { create?: boolean; can?: Record<string, boolean> } = {}) {
    setPageProps({ can, errors: {} });

    return render(
        <UsersIndex
            users={page([tomas, ines])}
            invitations={[]}
            filters={{ role: null, search: '' }}
            counts={{ all: 2, admin: 1, editor: 1, seo_manager: 1 }}
            roleTabs={[
                { value: 'admin', label: 'Administrator' },
                { value: 'editor', label: 'Editor' },
                { value: 'seo_manager', label: 'SEO manager' },
            ]}
            can={{ create }}
            roles={[
                { value: 'editor', label: 'Editor' },
                { value: 'author', label: 'Author' },
            ]}
            listView={{ key: 'users', columns: null, density: null }}
            reassignOptions={[]}
        />,
    );
}

const rowOf = (name: string) =>
    screen.getAllByText(name)[0].closest('tr') as HTMLElement;

describe('Users list', () => {
    it('builds the filter tabs from every role', () => {
        renderUsers();

        const tabs = screen.getByRole('navigation', {
            name: 'Filter users by role',
        });
        expect(
            within(tabs).getByRole('link', { name: /SEO manager/ }),
        ).toHaveAttribute('href', '/admin/users?role=seo_manager');
        expect(within(tabs).getByRole('link', { name: /All/ })).toHaveAttribute(
            'href',
            '/admin/users',
        );
    });

    it('shows the role picker only for people the viewer may promote', () => {
        renderUsers();

        expect(
            within(rowOf('Tomás Aguilar')).getByRole('combobox'),
        ).toBeInTheDocument();
        expect(
            within(rowOf('Inês Carvalho')).queryByRole('combobox'),
        ).not.toBeInTheDocument();
        expect(
            within(rowOf('Inês Carvalho')).getByText('Administrator'),
        ).toBeInTheDocument();
    });

    it('shows extra roles as small badges', () => {
        renderUsers();

        expect(
            within(rowOf('Tomás Aguilar')).getByText('SEO manager'),
        ).toBeInTheDocument();
    });

    it('offers manage access and delete only when allowed', async () => {
        const user = userEvent.setup();
        renderUsers();

        await user.click(
            screen.getAllByRole('button', {
                name: 'Actions for Tomás Aguilar',
            })[0],
        );
        expect(
            screen.getByRole('menuitem', { name: 'Manage access' }),
        ).toHaveAttribute('href', '/admin/users/9/access');
        expect(
            screen.getByRole('menuitem', { name: 'Delete user' }),
        ).toBeInTheDocument();
        await user.keyboard('{Escape}');

        expect(
            screen.queryByRole('button', { name: 'Actions for Inês Carvalho' }),
        ).not.toBeInTheDocument();
    });

    it('hides add and invite without the create permission', () => {
        renderUsers({ create: false });

        expect(
            screen.queryByRole('button', { name: 'Add user' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Invite' }),
        ).not.toBeInTheDocument();
    });

    it('shows add and invite with the create permission', () => {
        renderUsers();

        expect(
            screen.getByRole('button', { name: 'Add user' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Invite' }),
        ).toBeInTheDocument();
    });

    it('links to the roles page only for people who manage roles', () => {
        const { unmount } = renderUsers();
        expect(
            screen.queryByRole('link', { name: 'Roles' }),
        ).not.toBeInTheDocument();
        unmount();

        renderUsers({ can: { manage_roles: true } });
        expect(screen.getByRole('link', { name: 'Roles' })).toHaveAttribute(
            'href',
            '/admin/roles',
        );
    });
});
