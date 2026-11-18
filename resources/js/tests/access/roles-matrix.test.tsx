import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import RolesIndex from '@/pages/admin/access/roles/index';
import { resetPageProps, router } from '@/tests/mocks/inertia';
import { audit, capabilities, grantable, roles } from './fixtures';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

afterEach(() => {
    resetPageProps();
    vi.clearAllMocks();
});

function renderMatrix() {
    return render(
        <RolesIndex
            roles={roles}
            capabilities={capabilities}
            grantable={grantable}
            audit={audit}
        />,
    );
}

const box = (name: string) => screen.getByRole('checkbox', { name });

describe('Roles matrix', () => {
    it('shows groups, sections and role columns in a labelled region', () => {
        renderMatrix();

        const region = screen.getByRole('region', {
            name: 'Capabilities of each role',
        });
        expect(region).toHaveAttribute('tabindex', '0');

        const table = within(region).getByRole('table');
        for (const heading of [
            'Content',
            'All content types',
            'Media',
            'Administration',
            'Users',
            'Settings',
            'SEO toolkit',
        ]) {
            expect(within(table).getByText(heading)).toBeInTheDocument();
        }
        expect(
            within(table).getByRole('link', { name: 'SEO manager' }),
        ).toHaveAttribute('href', '/admin/roles/seo_manager');
        expect(within(table).getByText('5 users')).toBeInTheDocument();
    });

    it('filters capabilities by label without accents or case', async () => {
        const user = userEvent.setup();
        renderMatrix();

        await user.type(
            screen.getByRole('searchbox', { name: 'Search capabilities' }),
            'USÉR',
        );

        expect(screen.getByText('Manage users')).toBeInTheDocument();
        expect(screen.getByText('See the user list')).toBeInTheDocument();
        expect(screen.queryByText('Upload files')).not.toBeInTheDocument();
        expect(screen.queryByText('Content')).not.toBeInTheDocument();
    });

    it('finds capabilities by key and by group', async () => {
        const user = userEvent.setup();
        renderMatrix();
        const search = screen.getByRole('searchbox', {
            name: 'Search capabilities',
        });

        await user.type(search, 'seo.redirects');
        expect(screen.getByText('Edit redirects')).toBeInTheDocument();
        expect(screen.queryByText('Publish entries')).not.toBeInTheDocument();

        await user.clear(search);
        await user.type(search, 'settings');
        expect(screen.getByText('Change site settings')).toBeInTheDocument();
        expect(screen.queryByText('Edit redirects')).not.toBeInTheDocument();
    });

    it('shows an empty state when nothing matches', async () => {
        const user = userEvent.setup();
        renderMatrix();

        await user.type(
            screen.getByRole('searchbox', { name: 'Search capabilities' }),
            'croissant',
        );

        expect(
            screen.getByText('No capability matches these filters.'),
        ).toBeInTheDocument();
        expect(screen.queryByRole('table')).not.toBeInTheDocument();
    });

    it('shows a tri-state group box for each role', () => {
        renderMatrix();

        expect(box('All Content capabilities for Editor')).toHaveAttribute(
            'aria-checked',
            'true',
        );
        expect(box('All Content capabilities for Author')).toHaveAttribute(
            'aria-checked',
            'mixed',
        );
        expect(box('All Content capabilities for SEO manager')).toHaveAttribute(
            'aria-checked',
            'mixed',
        );
        expect(box('All Settings capabilities for Editor')).toHaveAttribute(
            'aria-checked',
            'false',
        );
    });

    it('ticks every grantable capability of a group and leaves the others', async () => {
        const user = userEvent.setup();
        renderMatrix();

        await user.click(box('All Administration capabilities for Author'));

        expect(box('Manage users for Author')).toBeChecked();
        expect(box('See the user list for Author')).toBeChecked();
        expect(box('Manage roles for Author')).not.toBeChecked();
        expect(box('Manage roles for Author')).toBeDisabled();
        expect(
            box('All Administration capabilities for Author'),
        ).toHaveAttribute('aria-checked', 'mixed');

        await user.click(box('All Administration capabilities for Author'));

        expect(box('Manage users for Author')).not.toBeChecked();
        expect(box('See the user list for Author')).not.toBeChecked();
    });

    it('clears a full group with one click', async () => {
        const user = userEvent.setup();
        renderMatrix();

        await user.click(box('All Content capabilities for Editor'));

        expect(box('Publish entries for Editor')).not.toBeChecked();
        expect(box('Upload files for Editor')).not.toBeChecked();
        expect(box('All Content capabilities for Editor')).toHaveAttribute(
            'aria-checked',
            'false',
        );
    });

    it('keeps the administrator column read only and fully checked', () => {
        renderMatrix();

        for (const label of [
            'Publish entries',
            'Manage roles',
            'Change site settings',
            'Edit redirects',
        ]) {
            const cell = box(`${label} for Administrator`);
            expect(cell).toBeChecked();
            expect(cell).toBeDisabled();
        }
        expect(
            box('All Content capabilities for Administrator'),
        ).toBeDisabled();
        expect(screen.getByText('Always every capability')).toBeInTheDocument();
    });

    it('disables boxes the viewer cannot give', () => {
        renderMatrix();

        expect(box('Change site settings for Editor')).toBeDisabled();
        expect(box('All Settings capabilities for Editor')).toBeDisabled();
        expect(box('Publish entries for Editor')).toBeEnabled();
    });

    it('marks a capability included by another one that is ticked', async () => {
        const user = userEvent.setup();
        renderMatrix();

        expect(
            screen.getByText('Included in Manage users'),
        ).toBeInTheDocument();
        await user.click(box('Manage users for Author'));

        const row = box('See the user list for Author').closest(
            'tr',
        ) as HTMLElement;
        expect(within(row).getByText('Included')).toBeInTheDocument();
    });

    it('saves only the changed roles with their full capability lists', async () => {
        const user = userEvent.setup();
        renderMatrix();

        await user.click(box('Delete entries of others for Author'));
        await user.click(box('Edit redirects for Editor'));

        expect(screen.getByRole('status')).toHaveTextContent(
            '2 roles changed: Editor and Author',
        );

        await user.click(screen.getByRole('button', { name: 'Save' }));

        expect(router.put).toHaveBeenCalledWith(
            '/admin/roles/matrix',
            {
                roles: {
                    editor: [
                        'publish_entries',
                        'delete_others_entries',
                        'upload_media',
                        'list_users',
                        'seo.redirects',
                    ],
                    author: [
                        'publish_entries',
                        'delete_others_entries',
                        'upload_media',
                    ],
                },
            },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('does not count a role changed back as changed', async () => {
        const user = userEvent.setup();
        renderMatrix();

        await user.click(box('Upload files for SEO manager'));
        expect(screen.getByRole('status')).toHaveTextContent('1 role changed');

        await user.click(box('Upload files for SEO manager'));
        expect(
            screen.queryByRole('button', { name: 'Save' }),
        ).not.toBeInTheDocument();
    });

    it('discards local changes', async () => {
        const user = userEvent.setup();
        renderMatrix();

        await user.click(box('Publish entries for Author'));
        expect(box('Publish entries for Author')).not.toBeChecked();

        await user.click(screen.getByRole('button', { name: 'Discard' }));

        expect(box('Publish entries for Author')).toBeChecked();
        expect(
            screen.queryByRole('button', { name: 'Save' }),
        ).not.toBeInTheDocument();
        expect(router.put).not.toHaveBeenCalled();
    });

    it('shows only rows where editable roles differ', async () => {
        const user = userEvent.setup();
        renderMatrix();

        await user.click(
            screen.getByRole('checkbox', { name: 'Show only differences' }),
        );

        expect(
            screen.getByText('Delete entries of others'),
        ).toBeInTheDocument();
        expect(screen.queryByText('Upload files')).not.toBeInTheDocument();
        expect(screen.queryByText('Manage roles')).not.toBeInTheDocument();
    });

    it('lists recent changes with role names and capability labels', () => {
        renderMatrix();

        expect(screen.getByText(/Changed capabilities/)).toBeInTheDocument();
        expect(screen.getByText('Added: Upload files')).toBeInTheDocument();
        expect(screen.getByText(/Given to Tomás Aguilar/)).toBeInTheDocument();
    });
});
