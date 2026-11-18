import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { AdminShell } from '@/components/verso/shell/admin-shell';
import { resetPageProps, router, setPageProps } from '@/tests/mocks/inertia';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

afterEach(() => {
    resetPageProps();
    vi.clearAllMocks();
});

function renderShell(viewAs: { role: string; name: string } | null) {
    setPageProps({
        auth: {
            user: {
                id: 3,
                name: 'Marta Ribeiro',
                email: 'marta@harborlane.pt',
                role: 'admin',
            },
        },
        viewAs,
    });

    return render(
        <AdminShell breadcrumbs={[{ title: 'Dashboard', href: '/admin' }]}>
            <h1>Dashboard</h1>
        </AdminShell>,
    );
}

describe('View as banner', () => {
    it('tells the admin which role they are previewing', () => {
        renderShell({ role: 'seo_manager', name: 'SEO manager' });

        const banner = screen.getByRole('region', { name: 'Role preview' });
        expect(within(banner).getByRole('status')).toHaveTextContent(
            'You are viewing the admin as SEO manager. This preview is read only.',
        );
    });

    it('exits the preview', async () => {
        const user = userEvent.setup();
        renderShell({ role: 'editor', name: 'Editor' });

        await user.click(screen.getByRole('button', { name: 'Exit preview' }));

        expect(router.delete).toHaveBeenCalledWith('/admin/view-as');
    });

    it('is hidden when there is no preview', () => {
        renderShell(null);

        expect(
            screen.queryByRole('region', { name: 'Role preview' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Exit preview' }),
        ).not.toBeInTheDocument();
    });
});
