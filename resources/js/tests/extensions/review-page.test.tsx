import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ExtensionReview from '@/pages/admin/extensions/review';
import { router } from '@/tests/mocks/inertia';
import { install } from '@/routes/admin/extensions';
import { capabilityLabels, stagedPlugin, verificationReport } from './fixtures';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

const accept = () => screen.getByRole('button', { name: 'Accept and install' });

describe('ExtensionReview', () => {
    beforeEach(() => {
        vi.mocked(router.post).mockClear();
    });

    it('lets a low risk plugin be installed at once', async () => {
        render(
            <ExtensionReview
                staged={stagedPlugin()}
                capabilities={capabilityLabels}
                verification={verificationReport({ risk: 'low' })}
                blockHighRisk={false}
            />,
        );

        expect(screen.queryByLabelText(/to confirm/)).toBeNull();
        expect(accept()).toBeEnabled();

        await userEvent.click(accept());

        expect(router.post).toHaveBeenCalledWith(
            install.url(stagedPlugin().token),
            { activate: false, confirm_slug: '' },
            expect.any(Object),
        );
    });

    it('asks for the slug before a high risk install', async () => {
        render(
            <ExtensionReview
                staged={stagedPlugin()}
                capabilities={capabilityLabels}
                verification={verificationReport()}
                blockHighRisk={false}
            />,
        );

        const input = screen.getByLabelText(/to confirm/);

        expect(accept()).toBeDisabled();

        await userEvent.type(input, 'backup');
        expect(accept()).toBeDisabled();

        await userEvent.type(input, '-runner');
        expect(accept()).toBeEnabled();

        await userEvent.click(accept());

        expect(router.post).toHaveBeenCalledWith(
            install.url(stagedPlugin().token),
            { activate: false, confirm_slug: 'backup-runner' },
            expect.any(Object),
        );
    });

    it('shows the server error for a wrong slug', async () => {
        vi.mocked(router.post).mockImplementation((_url, _data, options) => {
            options?.onError?.({
                confirm_slug:
                    'This plugin is high risk. Type "backup-runner" to confirm the install.',
            });
        });

        render(
            <ExtensionReview
                staged={stagedPlugin()}
                capabilities={capabilityLabels}
                verification={verificationReport()}
                blockHighRisk={false}
            />,
        );

        await userEvent.type(
            screen.getByLabelText(/to confirm/),
            'backup-runner',
        );
        await userEvent.click(accept());

        expect(
            await screen.findByText(
                /Type "backup-runner" to confirm the install/,
            ),
        ).toBeInTheDocument();
    });

    it('blocks a high risk plugin when the site says so', () => {
        render(
            <ExtensionReview
                staged={stagedPlugin()}
                capabilities={capabilityLabels}
                verification={verificationReport()}
                blockHighRisk
            />,
        );

        expect(
            screen.getByText(/this site blocks high risk plugins/),
        ).toBeInTheDocument();
        expect(screen.queryByLabelText(/to confirm/)).toBeNull();
        expect(accept()).toBeDisabled();
    });

    it('shows the declared network hosts', () => {
        render(
            <ExtensionReview
                staged={stagedPlugin()}
                capabilities={capabilityLabels}
                verification={null}
                blockHighRisk={false}
            />,
        );

        expect(
            screen.getByText('Network hosts: backups.harbourlights.io'),
        ).toBeInTheDocument();
        expect(screen.queryByText('Verification')).toBeNull();
    });
});
