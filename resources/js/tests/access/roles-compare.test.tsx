import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import RolesCompare from '@/pages/admin/access/roles/compare';
import { resetPageProps, router } from '@/tests/mocks/inertia';
import { capabilities, roles } from './fixtures';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

afterEach(() => {
    resetPageProps();
    vi.clearAllMocks();
});

function renderCompare(selected = ['editor', 'author'], limit = 3) {
    return render(
        <RolesCompare
            selected={selected}
            roles={roles}
            capabilities={capabilities}
            limit={limit}
        />,
    );
}

const rowOf = (label: string) =>
    screen.getByText(label).closest('tr') as HTMLElement;

describe('Compare roles', () => {
    it('shows a column per picked role with yes and no marks', () => {
        renderCompare();

        const table = within(
            screen.getByRole('region', {
                name: 'Capabilities of the roles you picked',
            }),
        ).getByRole('table');
        expect(
            within(table).getByRole('columnheader', { name: /Editor/ }),
        ).toBeInTheDocument();
        expect(
            within(table).queryByRole('columnheader', { name: /SEO manager/ }),
        ).not.toBeInTheDocument();

        const cells = within(rowOf('Delete entries of others')).getAllByRole(
            'cell',
        );
        expect(cells.map((cell) => cell.textContent)).toEqual(['Yes', 'No']);
    });

    it('highlights rows where the roles differ', () => {
        renderCompare();

        expect(rowOf('Delete entries of others')).toHaveAttribute(
            'data-differs',
            'true',
        );
        expect(rowOf('Publish entries')).not.toHaveAttribute('data-differs');
        expect(screen.getByText('2 capabilities differ')).toBeInTheDocument();
    });

    it('counts implied capabilities as given', () => {
        renderCompare(['admin', 'editor']);

        const cells = within(rowOf('See the user list')).getAllByRole('cell');
        expect(cells.map((cell) => cell.textContent)).toEqual(['Yes', 'Yes']);
    });

    it('shows only the differences when asked', async () => {
        const user = userEvent.setup();
        renderCompare();

        await user.click(
            screen.getByRole('checkbox', { name: 'Show only differences' }),
        );

        expect(
            screen.getByText('Delete entries of others'),
        ).toBeInTheDocument();
        expect(screen.getByText('See the user list')).toBeInTheDocument();
        expect(screen.queryByText('Publish entries')).not.toBeInTheDocument();
        expect(screen.queryByText('Edit redirects')).not.toBeInTheDocument();
    });

    it('updates the address when a role is picked', async () => {
        const user = userEvent.setup();
        renderCompare();

        await user.click(screen.getByRole('checkbox', { name: 'SEO manager' }));

        expect(router.get).toHaveBeenCalledWith(
            '/admin/roles/compare?roles=editor%2Cauthor%2Cseo_manager',
            {},
            expect.objectContaining({ preserveState: true }),
        );
    });

    it('stops at the limit', () => {
        renderCompare(['editor', 'author'], 2);

        expect(
            screen.getByRole('checkbox', { name: 'SEO manager' }),
        ).toBeDisabled();
        expect(screen.getByRole('checkbox', { name: 'Editor' })).toBeEnabled();
        expect(
            screen.getByText(
                'You can compare up to 2 roles. Clear one to pick another.',
            ),
        ).toBeInTheDocument();
    });
});
