import { render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { DashboardTabs } from '@/components/verso/dashboard/dashboard-tabs';
import {
    resetPageProps,
    setPageProps,
    setPageUrl,
} from '@/tests/mocks/inertia';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

afterEach(() => resetPageProps());

const dashboard = {
    key: 'dashboard',
    label: 'Dashboard',
    href: '/admin',
    icon: 'dashboard',
    group: 'dashboard' as const,
};
const analytics = {
    key: 'analytics.overview',
    label: 'Analytics',
    href: '/admin/analytics',
    icon: 'analytics',
    group: 'dashboard' as const,
    parent: 'dashboard',
};

describe('DashboardTabs', () => {
    it('shows Overview and the dashboard pages of plugins', () => {
        setPageProps({ versoNav: [dashboard, analytics] });
        setPageUrl('/admin/analytics?range=30');

        render(<DashboardTabs />);

        expect(screen.getByRole('link', { name: 'Overview' })).toHaveAttribute(
            'href',
            '/admin',
        );
        expect(screen.getByRole('link', { name: 'Analytics' })).toHaveAttribute(
            'aria-current',
            'page',
        );
    });

    it('marks Overview on the dashboard', () => {
        setPageProps({ versoNav: [dashboard, analytics] });

        render(<DashboardTabs />);

        expect(screen.getByRole('link', { name: 'Overview' })).toHaveAttribute(
            'aria-current',
            'page',
        );
    });

    it('shows nothing when no plugin adds a dashboard page', () => {
        setPageProps({ versoNav: [dashboard] });

        const { container } = render(<DashboardTabs />);

        expect(container).toBeEmptyDOMElement();
    });
});
