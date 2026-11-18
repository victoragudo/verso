import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Dashboard from '@/pages/admin/core/dashboard';
import { setPageProps } from '@/tests/mocks/inertia';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

beforeEach(() => {
    setPageProps({
        auth: { user: { name: 'Ines Navarro' } },
    });
});

describe('Dashboard notices', () => {
    it('shows a notice above the stats', () => {
        render(
            <Dashboard
                stats={[]}
                panels={[]}
                notices={[
                    {
                        key: 'core-update',
                        icon: 'updates',
                        title: 'Verso 1.2.0 is available',
                        description:
                            'You run Verso 1.1.4. Read what is new and update in one click.',
                        href: '/admin/updates',
                        action: 'See the update',
                        tone: 'info',
                    },
                ]}
            />,
        );

        expect(screen.getByRole('status')).toHaveTextContent(
            'Verso 1.2.0 is available',
        );
        expect(
            screen.getByRole('link', { name: 'See the update' }),
        ).toHaveAttribute('href', '/admin/updates');
    });

    it('shows no notice box without notices', () => {
        render(<Dashboard stats={[]} panels={[]} />);

        expect(screen.queryByRole('status')).toBeNull();
        expect(
            screen.getByRole('heading', { name: 'Welcome back, Ines' }),
        ).toBeInTheDocument();
    });
});

describe('Dashboard grids', () => {
    it('fits stats to the width and stacks panels in a masonry', () => {
        render(
            <Dashboard
                stats={[
                    {
                        key: 'posts',
                        label: 'Published posts',
                        value: 42,
                        href: '/admin/entries/post',
                        icon: 'entries',
                    },
                ]}
                panels={[
                    {
                        key: 'recent',
                        label: 'Recent entries',
                        icon: 'entries',
                        items: [],
                        emptyText: 'No entries yet.',
                        href: null,
                    },
                ]}
            />,
        );

        const stats = screen
            .getByText('Published posts')
            .closest('.auto-fit-grid');
        const panels = screen
            .getByText('Recent entries')
            .closest('.masonry-grid');

        expect(stats).not.toBeNull();
        expect(panels).not.toBeNull();
        expect(panels).not.toBe(stats);
        expect(stats?.className).not.toMatch(/grid-cols-\d/);
    });
});
