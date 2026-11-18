import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import UserAccess from '@/pages/admin/access/users/access';
import { resetPageProps, router, setPageProps } from '@/tests/mocks/inertia';
import type { AccessSubject } from '@/types';
import { capabilities, grantable, roles } from './fixtures';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

afterEach(() => {
    resetPageProps();
    vi.clearAllMocks();
});

const marta: AccessSubject = {
    id: 14,
    name: 'Marta Ribeiro',
    email: 'marta@harborlane.pt',
    role: 'author',
    extraRoles: ['seo_manager'],
    overrides: { delete_others_entries: true, upload_media: false },
    effective: ['publish_entries', 'delete_others_entries', 'seo.redirects'],
    isAdmin: false,
    isSelf: false,
};

function renderAccess(user: AccessSubject = marta) {
    return render(
        <UserAccess
            user={user}
            mainRoles={['admin', 'editor', 'author', 'contributor']}
            roles={roles}
            capabilities={capabilities}
            grantable={grantable}
            audit={[]}
        />,
    );
}

const control = (label: string) => screen.getByRole('group', { name: label });
const option = (label: string, value: string) =>
    within(control(label)).getByRole('radio', { name: value });
const rowOf = (label: string) => control(label).closest('li') as HTMLElement;

describe('User access page', () => {
    it('starts from the saved grants and denies', () => {
        renderAccess();

        expect(option('Delete entries of others', 'Grant')).toBeChecked();
        expect(option('Upload files', 'Deny')).toBeChecked();
        expect(option('Publish entries', 'From roles')).toBeChecked();
        expect(
            screen.getByRole('checkbox', { name: 'SEO manager' }),
        ).toBeChecked();
        expect(
            screen.queryByRole('checkbox', { name: 'Author' }),
        ).not.toBeInTheDocument();
    });

    it('previews the effective access while editing', async () => {
        const user = userEvent.setup();
        renderAccess();

        expect(
            screen.getByText('Marta Ribeiro can use 3 of 8 capabilities'),
        ).toBeInTheDocument();
        expect(
            within(rowOf('Upload files')).getByText('Not allowed'),
        ).toBeInTheDocument();

        await user.click(option('Upload files', 'From roles'));
        expect(
            within(rowOf('Upload files')).getByText('Will be allowed'),
        ).toBeInTheDocument();

        await user.click(option('Manage users', 'Grant'));
        expect(
            within(rowOf('See the user list')).getByText('Will be allowed'),
        ).toBeInTheDocument();

        await user.click(option('Publish entries', 'Deny'));
        expect(
            within(rowOf('Publish entries')).getByText('Will be taken'),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Marta Ribeiro can use 5 of 8 capabilities'),
        ).toBeInTheDocument();
    });

    it('drops the extra roles when the person loses them', async () => {
        const user = userEvent.setup();
        renderAccess();

        await user.click(screen.getByRole('checkbox', { name: 'SEO manager' }));

        expect(
            within(rowOf('Edit redirects')).getByText('Will be taken'),
        ).toBeInTheDocument();
    });

    it('sends the role, extra roles, grants and denies', async () => {
        const user = userEvent.setup();
        renderAccess();

        await user.click(option('Delete entries of others', 'From roles'));
        await user.click(option('Manage users', 'Grant'));
        await user.click(option('Edit redirects', 'Deny'));
        await user.click(screen.getAllByRole('button', { name: 'Save' })[0]);

        expect(router.put).toHaveBeenCalledWith(
            '/admin/users/14/access',
            {
                role: 'author',
                extra_roles: ['seo_manager'],
                grants: ['manage_users'],
                denies: ['upload_media', 'seo.redirects'],
            },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('keeps save off until something changes and can discard', async () => {
        const user = userEvent.setup();
        renderAccess();

        expect(screen.getByRole('button', { name: 'Save' })).toBeDisabled();
        await user.click(option('Publish entries', 'Deny'));
        await user.click(screen.getByRole('button', { name: 'Discard' }));

        expect(option('Publish entries', 'From roles')).toBeChecked();
        expect(screen.getByRole('button', { name: 'Save' })).toBeDisabled();
    });

    it('does not let the viewer grant what they do not have', () => {
        renderAccess();

        expect(option('Change site settings', 'Grant')).toBeDisabled();
        expect(option('Change site settings', 'Deny')).toBeEnabled();
        expect(option('Publish entries', 'Grant')).toBeEnabled();
    });

    it('warns that denies do not apply to administrators', () => {
        renderAccess({
            ...marta,
            role: 'admin',
            isAdmin: true,
            effective: capabilities.map((item) => item.key),
        });

        expect(
            screen.getByText('Administrators always have every capability'),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Marta Ribeiro can use 8 of 8 capabilities'),
        ).toBeInTheDocument();
        expect(
            within(rowOf('Upload files')).getByText('Allowed'),
        ).toBeInTheDocument();
    });

    it('shows no admin warning for other roles', () => {
        renderAccess();

        expect(
            screen.queryByText('Administrators always have every capability'),
        ).not.toBeInTheDocument();
    });

    it('shows errors from the server', () => {
        setPageProps({
            errors: {
                grants: 'You cannot give Change site settings because you do not have it.',
                'denies.0':
                    'A capability cannot be given and taken away at the same time.',
                name: 'Ignored here',
            },
        });
        renderAccess();

        const alert = screen.getByRole('alert');
        expect(alert).toHaveTextContent('You cannot give Change site settings');
        expect(alert).toHaveTextContent(
            'A capability cannot be given and taken away',
        );
        expect(alert).not.toHaveTextContent('Ignored here');
    });
});
