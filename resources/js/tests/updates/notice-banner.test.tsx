import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { NoticeBanner } from '@/components/verso/ui/notice-banner';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

describe('NoticeBanner', () => {
    it('shows the title, the description and the action link', () => {
        render(
            <NoticeBanner
                title="Verso 1.2.0 is available"
                description="You run Verso 1.1.4."
                href="/admin/updates"
                action="See the update"
            />,
        );

        expect(screen.getByRole('status')).toHaveTextContent(
            'Verso 1.2.0 is available',
        );
        expect(screen.getByText('You run Verso 1.1.4.')).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'See the update' }),
        ).toHaveAttribute('href', '/admin/updates');
    });

    it('leaves out the link without an action label', () => {
        render(<NoticeBanner title="Backups are on" href="/admin/updates" />);

        expect(screen.queryByRole('link')).toBeNull();
    });

    it('uses the soft colors of the tone', () => {
        render(<NoticeBanner title="Security update" tone="warning" />);

        expect(screen.getByRole('status').className).toContain(
            'bg-warning-soft',
        );
    });
});
