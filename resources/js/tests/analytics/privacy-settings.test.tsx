import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AnalyticsPage from '@/pages/admin/plugins/analytics/index';

const put = vi.fn();
let lastData: Record<string, unknown> = {};

// useForm keeps its data in React state, like the real one, and records what is sent.
vi.mock('@inertiajs/react', async () => {
    const { inertiaMock } = await import('@/tests/mocks/inertia');

    return {
        ...inertiaMock,
        useForm: <T extends Record<string, unknown>>(initial: T) => {
            const [data, setState] = useState<T>(initial);
            lastData = data;

            return {
                data,
                errors: {},
                processing: false,
                setData: (key: keyof T, value: unknown) =>
                    setState((current) => ({ ...current, [key]: value })),
                put: (url: string, options: unknown) => put(url, options),
            };
        },
    };
});

const emptyReport = {
    from: '2026-10-19',
    to: '2026-11-17',
    totals: { visitors: 0, views: 0 },
    series: [],
    pages: [],
    referrers: [],
    devices: [],
};

const roleOptions = [
    { value: 'admin', label: 'Admin' },
    { value: 'editor', label: 'Editor' },
    { value: 'author', label: 'Author' },
    { value: 'shop_manager', label: 'Shop manager' },
];

function renderPage(ignored: string[]) {
    return render(
        <AnalyticsPage
            range={30}
            ranges={[{ days: 30, href: '/admin/analytics?range=30' }]}
            report={emptyReport}
            settings={{ retention_days: 30, ignore_roles: ignored }}
            roleOptions={roleOptions}
            retentionLimits={{ min: 1, max: 365 }}
            settingsUrl="/admin/analytics/settings"
        />,
    );
}

describe('analytics privacy settings', () => {
    beforeEach(() => {
        put.mockClear();
        lastData = {};
    });

    it('shows one checkbox per role, checked for the ignored ones', () => {
        renderPage(['admin']);

        expect(
            screen.getByRole('checkbox', { name: 'Ignore Admin' }),
        ).toBeChecked();
        expect(
            screen.getByRole('checkbox', { name: 'Ignore Editor' }),
        ).not.toBeChecked();
        expect(
            screen.getByRole('checkbox', { name: 'Ignore Shop manager' }),
        ).toBeInTheDocument();
    });

    it('adds and removes roles before saving', async () => {
        const user = userEvent.setup();
        renderPage(['admin']);

        await user.click(
            screen.getByRole('checkbox', { name: 'Ignore Editor' }),
        );
        await user.click(
            screen.getByRole('checkbox', { name: 'Ignore Admin' }),
        );
        await user.click(screen.getByRole('button', { name: 'Save settings' }));

        expect(lastData.ignore_roles).toEqual(['editor']);
        expect(put).toHaveBeenCalledWith('/admin/analytics/settings', {
            preserveScroll: true,
        });
    });
});
