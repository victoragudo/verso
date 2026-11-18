import { act, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import ThemesIndex from '@/pages/admin/theme/index';
import { router } from '@/tests/mocks/inertia';
import type { ThemeInfo } from '@/types';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

const base: ThemeInfo = {
    slug: 'default',
    name: 'Verso Default',
    version: '1.1.0',
    description: 'A calm, fast and accessible theme for reading.',
    author: 'The Verso contributors',
    active: true,
    removable: false,
    customizable: true,
    parent: null,
    parentName: null,
    missingParent: null,
    templates: 1,
    patterns: 3,
    parts: 2,
    risk: null,
};

const themes: ThemeInfo[] = [
    {
        ...base,
        slug: 'harbor-dusk',
        name: 'Harbor Dusk',
        active: false,
        removable: true,
        parent: 'default',
        parentName: 'Verso Default',
        templates: 0,
        patterns: 1,
        parts: 0,
    },
    {
        ...base,
        slug: 'lonely-dock',
        name: 'Lonely Dock',
        active: false,
        removable: true,
        parent: 'missing-pier',
        missingParent: 'missing-pier',
        templates: 0,
        patterns: 0,
        parts: 0,
    },
    base,
];

function card(name: string): HTMLElement {
    return screen.getByRole('heading', { name }).closest('article')!;
}

describe('Themes page', () => {
    it('locks the activate and cache buttons while a request runs', async () => {
        const user = userEvent.setup();
        vi.mocked(router.post).mockClear();
        render(
            <ThemesIndex
                themes={themes}
                pageCache={{ enabled: true, ttl: 3600 }}
            />,
        );

        await user.click(
            within(card('Harbor Dusk')).getByRole('button', {
                name: 'Activate',
            }),
        );
        const options = vi.mocked(router.post).mock.calls[0][2] as {
            onStart: () => void;
            onFinish: () => void;
        };

        act(() => options.onStart());
        expect(
            within(card('Harbor Dusk')).getByRole('button', {
                name: 'Activate',
            }),
        ).toBeDisabled();
        expect(
            screen.getByRole('button', { name: 'Clear page cache' }),
        ).toBeDisabled();

        act(() => options.onFinish());
        expect(
            screen.getByRole('button', { name: 'Clear page cache' }),
        ).toBeEnabled();
    });

    it('shows the parent of a child theme', () => {
        render(
            <ThemesIndex
                themes={themes}
                pageCache={{ enabled: true, ttl: 3600 }}
            />,
        );

        expect(
            within(card('Harbor Dusk')).getByText(/Child of/),
        ).toHaveTextContent('Child of Verso Default');
        expect(
            within(card('Verso Default')).queryByText(/Child of/),
        ).toBeNull();
    });

    it('shows what each theme ships', () => {
        render(
            <ThemesIndex
                themes={themes}
                pageCache={{ enabled: true, ttl: 3600 }}
            />,
        );

        expect(
            within(card('Verso Default')).getByText(
                '1 template · 3 patterns · 2 parts',
            ),
        ).toBeInTheDocument();
        expect(
            within(card('Harbor Dusk')).getByText('1 pattern'),
        ).toBeInTheDocument();
    });

    it('warns about a missing parent and blocks activation', async () => {
        render(
            <ThemesIndex
                themes={themes}
                pageCache={{ enabled: true, ttl: 3600 }}
            />,
        );

        const lonely = card('Lonely Dock');

        expect(
            within(lonely).getByText('Parent theme missing-pier is missing'),
        ).toBeInTheDocument();
        expect(within(lonely).getByText(/Child of/)).toHaveTextContent(
            'Child of missing-pier',
        );
        expect(
            within(lonely).getByRole('button', { name: 'Activate' }),
        ).toBeDisabled();

        await userEvent.click(
            within(card('Harbor Dusk')).getByRole('button', {
                name: 'Activate',
            }),
        );

        expect(router.post).toHaveBeenCalledWith(
            '/admin/themes/harbor-dusk/activate',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('shows the risk of checked themes and links each theme to its details', () => {
        render(
            <ThemesIndex
                themes={[{ ...base, risk: 'medium' }, themes[0]]}
                pageCache={{ enabled: true, ttl: 3600 }}
            />,
        );

        const checked = card('Verso Default');
        const unchecked = card('Harbor Dusk');

        expect(within(checked).getByText('Medium risk')).toBeInTheDocument();
        expect(
            within(checked).getByRole('link', { name: 'Details' }),
        ).toHaveAttribute('href', '/admin/themes/default');
        expect(within(unchecked).queryByText(/risk$/)).toBeNull();
        expect(
            within(unchecked).getByRole('link', { name: 'Check' }),
        ).toHaveAttribute('href', '/admin/themes/harbor-dusk');
    });
});
