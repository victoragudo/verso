import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import RoleShow from '@/pages/admin/access/roles/show';
import { resetPageProps, router } from '@/tests/mocks/inertia';
import type { AccessRole, RoleMembers } from '@/types';
import {
    administrator,
    audit,
    author,
    capabilities,
    editor,
    grantable,
    roles,
    seoManager,
} from './fixtures';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

afterEach(() => {
    resetPageProps();
    vi.clearAllMocks();
});

const members: RoleMembers = {
    total: 7,
    items: [
        {
            id: 9,
            name: 'Tomás Aguilar',
            email: 'tomas@harborlane.pt',
            main: true,
        },
        {
            id: 12,
            name: 'Inês Carvalho',
            email: 'ines@harborlane.pt',
            main: false,
        },
    ],
};

function renderRole(
    role: AccessRole,
    defaults: string[] | null,
    canViewAs = true,
) {
    return render(
        <RoleShow
            role={role}
            defaults={defaults}
            capabilities={capabilities}
            grantable={grantable}
            roles={roles.map(({ slug, name }) => ({ slug, name }))}
            members={members}
            audit={audit}
            canViewAs={canViewAs}
        />,
    );
}

const box = (name: string) => screen.getByRole('checkbox', { name });

describe('Role page', () => {
    it('marks capabilities added and removed versus the defaults', () => {
        renderRole(author, ['publish_entries', 'delete_others_entries']);

        const added = box('Upload files').closest('li') as HTMLElement;
        const removed = box('Delete entries of others').closest(
            'li',
        ) as HTMLElement;

        expect(within(added).getByText('Added')).toBeInTheDocument();
        expect(within(removed).getByText('Removed')).toBeInTheDocument();
        expect(
            screen.getByText(
                /1 added and 1 removed compared with the defaults/,
            ),
        ).toBeInTheDocument();
    });

    it('offers reset only for a modified built-in role and hides delete', () => {
        renderRole(author, ['publish_entries']);

        expect(
            screen.getByRole('button', { name: 'Reset to defaults' }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Delete' }),
        ).not.toBeInTheDocument();
    });

    it('hides reset for a built-in role that was not changed', () => {
        renderRole(editor, editor.capabilities);

        expect(
            screen.queryByRole('button', { name: 'Reset to defaults' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Delete' }),
        ).not.toBeInTheDocument();
    });

    it('resets after a confirm', async () => {
        const user = userEvent.setup();
        renderRole(author, ['publish_entries']);

        await user.click(
            screen.getByRole('button', { name: 'Reset to defaults' }),
        );
        const dialog = screen.getByRole('dialog');
        await user.click(
            within(dialog).getByRole('button', { name: 'Reset to defaults' }),
        );

        expect(router.post).toHaveBeenCalledWith(
            '/admin/roles/author/reset',
            {},
            expect.anything(),
        );
    });

    it('deletes a custom role after saying how many people lose it', async () => {
        const user = userEvent.setup();
        renderRole(seoManager, null);

        expect(
            screen.queryByRole('button', { name: 'Reset to defaults' }),
        ).not.toBeInTheDocument();
        await user.click(screen.getByRole('button', { name: 'Delete' }));

        const dialog = screen.getByRole('dialog');
        expect(dialog).toHaveTextContent(
            '1 person has this role and will lose it.',
        );
        await user.click(
            within(dialog).getByRole('button', { name: 'Delete role' }),
        );

        expect(router.delete).toHaveBeenCalledWith(
            '/admin/roles/seo_manager',
            expect.anything(),
        );
    });

    it('does not show a diff for custom roles', () => {
        renderRole(seoManager, null);

        expect(screen.queryByText('Added')).not.toBeInTheDocument();
        expect(screen.queryByText('Removed')).not.toBeInTheDocument();
    });

    it('saves the name, description and capabilities', async () => {
        const user = userEvent.setup();
        renderRole(seoManager, null);

        expect(screen.getByRole('button', { name: 'Save' })).toBeDisabled();

        const name = screen.getByLabelText('Name');
        await user.clear(name);
        await user.type(name, 'Search editor');
        await user.click(box('Publish entries'));

        expect(screen.getByRole('status')).toHaveTextContent(
            'This role has unsaved changes.',
        );
        await user.click(screen.getAllByRole('button', { name: 'Save' })[0]);

        expect(router.put).toHaveBeenCalledWith(
            '/admin/roles/seo_manager',
            {
                name: 'Search editor',
                description: 'Looks after redirects and search snippets.',
                capabilities: [
                    'publish_entries',
                    'upload_media',
                    'seo.redirects',
                ],
            },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('selects all capabilities of a group that the viewer may give', async () => {
        const user = userEvent.setup();
        renderRole(seoManager, null);

        await user.click(box('All Administration capabilities'));

        expect(box('Manage users')).toBeChecked();
        expect(box('See the user list')).toBeChecked();
        expect(box('Manage roles')).not.toBeChecked();
        expect(box('Manage roles')).toBeDisabled();
    });

    it('filters the capability cards with the search box', async () => {
        const user = userEvent.setup();
        renderRole(editor, editor.capabilities);

        await user.type(
            screen.getByRole('searchbox', { name: 'Search capabilities' }),
            'redirect',
        );

        expect(box('Edit redirects')).toBeInTheDocument();
        expect(
            screen.queryByRole('checkbox', { name: 'Publish entries' }),
        ).not.toBeInTheDocument();
    });

    it('keeps the administrator role read only', () => {
        renderRole(administrator, null, false);

        expect(
            screen.getByText(
                'This role cannot be changed, so nobody can lock the site out.',
            ),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Save' }),
        ).not.toBeInTheDocument();
        expect(screen.queryByLabelText('Name')).not.toBeInTheDocument();
        expect(box('Manage roles')).toBeDisabled();
        expect(box('Manage roles')).toBeChecked();
        expect(
            screen.queryByRole('button', {
                name: 'View the admin as this role',
            }),
        ).not.toBeInTheDocument();
    });

    it('starts a preview as this role', async () => {
        const user = userEvent.setup();
        renderRole(editor, editor.capabilities);

        await user.click(
            screen.getByRole('button', { name: 'View the admin as this role' }),
        );

        expect(router.post).toHaveBeenCalledWith(
            '/admin/roles/editor/view-as',
            {},
            expect.anything(),
        );
    });

    it('clones the role with a new name', async () => {
        const user = userEvent.setup();
        renderRole(editor, editor.capabilities);

        await user.click(screen.getByRole('button', { name: 'Clone' }));
        const input = within(screen.getByRole('dialog')).getByLabelText(
            'Name of the new role',
        );
        await user.clear(input);
        await user.type(input, 'Night editor');
        await user.click(screen.getByRole('button', { name: 'Clone role' }));

        expect(router.post).toHaveBeenCalledWith(
            '/admin/roles/editor/clone',
            { name: 'Night editor' },
            expect.anything(),
        );
    });

    it('lists members with links to their access page', () => {
        renderRole(editor, editor.capabilities);

        expect(
            screen.getByRole('link', { name: 'Tomás Aguilar' }),
        ).toHaveAttribute('href', '/admin/users/9/access');
        expect(screen.getByText('7 people have this role')).toBeInTheDocument();
        expect(screen.getByText('And 5 more people.')).toBeInTheDocument();
        expect(screen.getAllByText('Main role')).toHaveLength(1);
    });

    it('links to compare with the role it is based on', () => {
        renderRole(seoManager, null);

        expect(screen.getByRole('link', { name: 'Compare' })).toHaveAttribute(
            'href',
            '/admin/roles/compare?roles=seo_manager%2Ceditor',
        );
    });
});
