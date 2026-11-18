import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PluginShow from '@/pages/admin/plugins/show';
import { router } from '@/tests/mocks/inertia';
import { remove } from '@/routes/admin/extensions';
import { disable, verify } from '@/routes/admin/plugins';
import type { PluginDetail } from '@/types';
import { capabilityLabels, verificationReport } from './fixtures';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

const plugin: PluginDetail = {
    slug: 'harbor-tides',
    name: 'Harbor Tides',
    version: '1.2.0',
    description: 'Shows the next high and low tides for your harbour.',
    requires: '^0.1',
    permissions: ['upload_files'],
    status: 'enabled',
    enabled: true,
    problems: [],
    removable: true,
    network: ['api.tides.example-marine.org'],
};

describe('PluginShow', () => {
    beforeEach(() => {
        vi.mocked(router.post).mockReset();
        vi.mocked(router.delete).mockReset();
    });

    it('offers to run the checks when the plugin was never checked', async () => {
        render(
            <PluginShow
                plugin={plugin}
                verification={null}
                capabilities={capabilityLabels}
                versoVersion="0.1.0"
            />,
        );

        expect(
            screen.getByText('This plugin has not been checked yet.'),
        ).toBeInTheDocument();

        await userEvent.click(
            screen.getByRole('button', { name: 'Run checks again' }),
        );

        expect(router.post).toHaveBeenCalledWith(
            verify.url('harbor-tides'),
            {},
            expect.any(Object),
        );
    });

    it('shows the newest report and the plugin details', () => {
        render(
            <PluginShow
                plugin={plugin}
                verification={verificationReport({
                    slug: 'harbor-tides',
                    source: 'installed',
                })}
                capabilities={capabilityLabels}
                versoVersion="0.1.0"
            />,
        );

        expect(screen.getAllByText('High risk')[0]).toBeInTheDocument();
        expect(
            screen.getByText(/from the installed files/),
        ).toBeInTheDocument();
        expect(
            screen.getByText('api.tides.example-marine.org'),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Verso ^0.1 (this site runs 0.1.0)'),
        ).toBeInTheDocument();
    });

    it('disables an enabled plugin', async () => {
        render(
            <PluginShow
                plugin={plugin}
                verification={null}
                capabilities={capabilityLabels}
                versoVersion="0.1.0"
            />,
        );

        await userEvent.click(screen.getByRole('button', { name: 'Disable' }));

        expect(router.post).toHaveBeenCalledWith(
            disable.url('harbor-tides'),
            {},
            { preserveScroll: true },
        );
    });

    it('removes the plugin with the design system dialog, not the browser confirm', async () => {
        const confirmSpy = vi.spyOn(window, 'confirm');
        render(
            <PluginShow
                plugin={plugin}
                verification={null}
                capabilities={capabilityLabels}
                versoVersion="0.1.0"
            />,
        );

        await userEvent.click(screen.getByRole('button', { name: 'Remove' }));

        expect(screen.getByRole('dialog')).toHaveTextContent(
            'Remove Harbor Tides?',
        );

        await userEvent.click(
            screen.getByRole('button', { name: 'Remove, keep data' }),
        );

        expect(confirmSpy).not.toHaveBeenCalled();
        expect(router.delete).toHaveBeenCalledWith(
            remove.url({ kind: 'plugin', slug: 'harbor-tides' }),
            expect.objectContaining({ data: { delete_data: false } }),
        );
        confirmSpy.mockRestore();
    });

    it('hides Remove when the plugin cannot be removed', () => {
        render(
            <PluginShow
                plugin={{ ...plugin, removable: false }}
                verification={null}
                capabilities={capabilityLabels}
                versoVersion="0.1.0"
            />,
        );

        expect(
            screen.queryByRole('button', { name: 'Remove' }),
        ).not.toBeInTheDocument();
    });
});
