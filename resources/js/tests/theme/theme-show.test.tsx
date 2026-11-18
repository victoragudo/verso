import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ThemeShow from '@/pages/admin/theme/show';
import { router } from '@/tests/mocks/inertia';
import { activate, customize, verify } from '@/routes/admin/themes';
import { themeReport } from '@/tests/extensions/fixtures';
import type { ThemeDetail } from '@/types';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

const harbor: ThemeDetail = {
    slug: 'harbor-light',
    name: 'Harbor Light',
    version: '1.2.0',
    description: 'A bright theme for coastal travel journals.',
    author: 'Ines Duarte',
    parent: null,
    parentName: null,
    missingParent: null,
    missingViews: [],
    active: false,
    removable: true,
    customizable: true,
};

describe('ThemeShow', () => {
    beforeEach(() => {
        vi.mocked(router.post).mockReset();
    });

    it('offers to run the checks when the theme was never checked', async () => {
        render(
            <ThemeShow
                theme={harbor}
                verification={null}
                versoVersion="0.1.0"
            />,
        );

        expect(
            screen.getByText('This theme has not been checked yet.'),
        ).toBeInTheDocument();

        await userEvent.click(
            screen.getByRole('button', { name: 'Run checks again' }),
        );

        expect(router.post).toHaveBeenCalledWith(
            verify.url('harbor-light'),
            {},
            expect.any(Object),
        );
    });

    it('shows the newest report with the theme details', () => {
        render(
            <ThemeShow
                theme={harbor}
                verification={themeReport({ source: 'installed' })}
                versoVersion="0.1.0"
            />,
        );

        expect(screen.getByText('Low risk')).toBeInTheDocument();
        expect(screen.getByText('Ines Duarte')).toBeInTheDocument();
        expect(screen.getByText('Verso 0.1.0')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Customize' })).toHaveAttribute(
            'href',
            customize.url('harbor-light'),
        );
    });

    it('activates the theme', async () => {
        render(
            <ThemeShow
                theme={harbor}
                verification={null}
                versoVersion="0.1.0"
            />,
        );

        await userEvent.click(screen.getByRole('button', { name: 'Activate' }));

        expect(router.post).toHaveBeenCalledWith(
            activate.url('harbor-light'),
            {},
            expect.any(Object),
        );
    });

    it('does not offer to activate a theme whose parent is missing, and lists the views it borrows', () => {
        render(
            <ThemeShow
                theme={{
                    ...harbor,
                    parent: 'seaside-base',
                    missingParent: 'seaside-base',
                    missingViews: ['archive', '404'],
                }}
                verification={null}
                versoVersion="0.1.0"
            />,
        );

        expect(screen.queryByRole('button', { name: 'Activate' })).toBeNull();
        expect(
            screen.getByText('The parent theme seaside-base is not installed.'),
        ).toBeInTheDocument();
        expect(screen.getByText('archive, 404')).toBeInTheDocument();
    });
});
