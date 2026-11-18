import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ExtensionReview from '@/pages/admin/extensions/review';
import { router } from '@/tests/mocks/inertia';
import { install } from '@/routes/admin/extensions';
import type { StagedExtension } from '@/types';
import { stagedPlugin, themeReport } from './fixtures';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

// The Harbor Light theme zip waiting on the consent screen.
const stagedTheme: StagedExtension = stagedPlugin({
    token: '5f0c3a1e-8d2b-4e7a-9c61-2b3d4e5f6a7b',
    package: {
        kind: 'theme',
        slug: 'harbor-light',
        name: 'Harbor Light',
        version: '1.2.0',
        description: 'A bright theme for coastal travel journals.',
        author: 'Ines Duarte',
        requires: '^0.1',
        permissions: [],
        network: [],
        fileCount: 11,
        unpackedBytes: 18432,
    },
});

const accept = () => screen.getByRole('button', { name: 'Accept and install' });

describe('ExtensionReview for themes', () => {
    beforeEach(() => {
        vi.mocked(router.post).mockClear();
    });

    it('installs a clean theme and shows its checks and preview', async () => {
        render(
            <ExtensionReview
                staged={stagedTheme}
                capabilities={{}}
                verification={themeReport()}
                blockHighRisk
            />,
        );

        expect(
            screen.getByRole('heading', { name: 'Requirements and quality' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { name: 'Sandbox render' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Verso ^0.1')).toBeInTheDocument();
        expect(
            screen.getByTitle('Preview of the home page'),
        ).toBeInTheDocument();

        await userEvent.click(accept());

        expect(router.post).toHaveBeenCalledWith(
            install.url(stagedTheme.token),
            { activate: false, confirm_slug: '' },
            expect.any(Object),
        );
    });

    it('never lets a theme with blocking findings be installed', () => {
        render(
            <ExtensionReview
                staged={stagedTheme}
                capabilities={{}}
                verification={themeReport({
                    risk: 'high',
                    blocking: ['theme.missing-view'],
                })}
                blockHighRisk={false}
            />,
        );

        expect(accept()).toBeDisabled();
        expect(screen.queryByLabelText(/to confirm/)).toBeNull();
        expect(
            screen.getByText(
                'This theme cannot be installed: theme.missing-view. Fix the findings and upload it again.',
            ),
        ).toBeInTheDocument();
        expect(
            within(screen.getByRole('alert')).getByText('theme.missing-view'),
        ).toBeInTheDocument();
    });

    it('asks for the slug of a high risk theme even when high risk plugins are blocked', async () => {
        render(
            <ExtensionReview
                staged={stagedTheme}
                capabilities={{}}
                verification={themeReport({ risk: 'high' })}
                blockHighRisk
            />,
        );

        expect(
            screen.getByText(
                'This theme is high risk. Read the findings before you install it.',
            ),
        ).toBeInTheDocument();
        expect(accept()).toBeDisabled();

        await userEvent.type(
            screen.getByLabelText(/to confirm/),
            'harbor-light',
        );

        expect(accept()).toBeEnabled();
    });
});
