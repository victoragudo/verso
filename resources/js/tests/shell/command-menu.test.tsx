import {
    act,
    fireEvent,
    render,
    renderHook,
    screen,
} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    CommandMenu,
    extraCommands,
    matchScore,
    normalize,
    searchCommands,
    toCommands,
    useCommandShortcut,
} from '@/components/verso/shell/command-menu';
import { setMessages } from '@/lib/i18n';
import type { VersoNavItem } from '@/types';
import { resetPageProps, router, setPageProps } from '@/tests/mocks/inertia';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

vi.mock('@/routes/profile', () => ({
    edit: () => ({ url: '/settings/profile', method: 'get' }),
}));

const nav: VersoNavItem[] = [
    {
        key: 'dashboard',
        label: 'Dashboard',
        href: '/admin',
        icon: 'dashboard',
        group: 'dashboard',
    },
    {
        key: 'entries.post',
        label: 'Posts',
        href: '/admin/entries/post',
        icon: 'posts',
        group: 'content',
    },
    {
        key: 'entries.page',
        label: 'Pages',
        href: '/admin/entries/page',
        icon: 'pages',
        group: 'content',
    },
    {
        key: 'media',
        label: 'Media',
        href: '/admin/media',
        icon: 'media',
        group: 'content',
    },
    {
        key: 'menus',
        label: 'Menus',
        href: '/admin/menus',
        icon: 'menus',
        group: 'design',
    },
    {
        key: 'settings.general',
        label: 'General',
        href: '/admin/settings',
        icon: 'settings',
        group: 'settings',
    },
];

function renderMenu() {
    const onOpenChange = vi.fn<(open: boolean) => void>();

    render(<CommandMenu open onOpenChange={onOpenChange} />);

    return { onOpenChange, input: screen.getByRole('combobox') };
}

const optionLabels = () =>
    screen
        .queryAllByRole('option')
        .map(
            (option) =>
                option.querySelector('[data-command-label]')?.textContent,
        );

beforeEach(() => setPageProps({ versoNav: nav }));

afterEach(() => resetPageProps());

describe('CommandMenu', () => {
    it('lists every page the user can see plus the profile', () => {
        renderMenu();

        expect(optionLabels()).toEqual([
            'Dashboard',
            'Posts',
            'Pages',
            'Media',
            'Menus',
            'General',
            'Your profile',
        ]);
    });

    it('shows a section label above each group', () => {
        renderMenu();

        ['General', 'Content', 'Design', 'Settings', 'Account'].forEach(
            (label) => {
                expect(
                    screen.getAllByText(label, { selector: 'p' }),
                ).toHaveLength(1);
            },
        );
    });

    it('filters by label without caring about case', async () => {
        const { input } = renderMenu();

        await userEvent.type(input, 'MEDIA');

        expect(optionLabels()).toEqual(['Media']);
    });

    it('filters by section name too', async () => {
        const { input } = renderMenu();

        await userEvent.type(input, 'content');

        expect(optionLabels()).toEqual(['Posts', 'Pages', 'Media']);
    });

    it('matches part of a word', async () => {
        const { input } = renderMenu();

        await userEvent.type(input, 'ag');

        expect(optionLabels()).toEqual(['Pages']);
    });

    it('ignores spaces around the query', async () => {
        const { input } = renderMenu();

        await userEvent.type(input, '  menus  ');

        expect(optionLabels()).toEqual(['Menus']);
    });

    it('finds the profile in the account section', async () => {
        const { input } = renderMenu();

        await userEvent.type(input, 'account');

        expect(optionLabels()).toEqual(['Your profile']);
    });

    it('says when nothing matches', async () => {
        const { input } = renderMenu();

        await userEvent.type(input, 'invoices');

        expect(optionLabels()).toEqual([]);
        expect(screen.getByText('No pages match “invoices”.')).toBeVisible();
    });

    it('marks the first result as active after each change', async () => {
        const { input } = renderMenu();

        await userEvent.keyboard('{ArrowDown}{ArrowDown}');
        await userEvent.type(input, 'p');

        expect(screen.getAllByRole('option')[0]).toHaveAttribute(
            'aria-selected',
            'true',
        );
        expect(input).toHaveAttribute(
            'aria-activedescendant',
            `verso-command-${'entries.post'}`,
        );
    });

    it('moves the active result with the arrow keys and wraps around', async () => {
        const { input } = renderMenu();

        await userEvent.type(input, 'content');

        fireEvent.keyDown(input, { key: 'ArrowDown' });
        expect(input).toHaveAttribute(
            'aria-activedescendant',
            'verso-command-entries.page',
        );

        fireEvent.keyDown(input, { key: 'ArrowDown' });
        fireEvent.keyDown(input, { key: 'ArrowDown' });
        expect(input).toHaveAttribute(
            'aria-activedescendant',
            'verso-command-entries.post',
        );

        fireEvent.keyDown(input, { key: 'ArrowUp' });
        expect(input).toHaveAttribute(
            'aria-activedescendant',
            'verso-command-media',
        );
    });

    it('opens the active result with Enter and closes', async () => {
        const { input, onOpenChange } = renderMenu();

        await userEvent.type(input, 'set');
        fireEvent.keyDown(input, { key: 'Enter' });

        expect(router.visit).toHaveBeenCalledWith('/admin/settings');
        expect(onOpenChange).toHaveBeenCalledWith(false);
    });

    it('does nothing on Enter when nothing matches', async () => {
        const { input } = renderMenu();

        await userEvent.type(input, 'invoices');
        fireEvent.keyDown(input, { key: 'Enter' });

        expect(router.visit).not.toHaveBeenCalled();
    });

    it('opens a result on click', async () => {
        renderMenu();

        await userEvent.click(screen.getByRole('option', { name: /^Menus/ }));

        expect(router.visit).toHaveBeenCalledWith('/admin/menus');
    });

    it('opens the profile page', async () => {
        renderMenu();

        await userEvent.click(
            screen.getByRole('option', { name: /^Your profile/ }),
        );

        expect(router.visit).toHaveBeenCalledWith('/settings/profile');
    });

    it('clears the query when it closes', async () => {
        const onOpenChange = vi.fn();
        const { rerender } = render(
            <CommandMenu open onOpenChange={onOpenChange} />,
        );

        await userEvent.type(screen.getByRole('combobox'), 'media');
        await userEvent.keyboard('{Escape}');

        expect(onOpenChange).toHaveBeenCalledWith(false);

        rerender(<CommandMenu open={false} onOpenChange={onOpenChange} />);
        rerender(<CommandMenu open onOpenChange={onOpenChange} />);

        expect(screen.getByRole('combobox')).toHaveValue('');
        expect(optionLabels()).toHaveLength(7);
    });

    it('shows only the profile when the user has no nav items', () => {
        setPageProps({ versoNav: [] });

        renderMenu();

        expect(optionLabels()).toEqual(['Your profile']);
    });
});

describe('CommandMenu search with children and keywords', () => {
    const shopNav: VersoNavItem[] = [
        ...nav,
        {
            key: 'plugin.shop',
            label: 'Shop',
            href: '/admin/shop/orders',
            icon: 'shop',
            group: 'tools',
            parent: null,
            keywords: ['store', 'tienda'],
        },
        {
            key: 'shop.orders',
            label: 'Orders',
            href: '/admin/shop/orders',
            icon: 'orders',
            group: 'tools',
            parent: 'plugin.shop',
            keywords: ['sales', 'purchases', 'pedidos', 'ventas'],
        },
        {
            key: 'shop.products',
            label: 'Products',
            href: '/admin/shop/products',
            icon: 'product',
            group: 'tools',
            parent: 'plugin.shop',
            keywords: ['inventory', 'stock', 'productos'],
        },
        {
            key: 'terms.category',
            label: 'Categories',
            href: '/admin/terms/category',
            icon: 'categories',
            group: 'content',
            parent: 'entries.post',
            keywords: ['categorías'],
        },
    ];

    beforeEach(() => setPageProps({ versoNav: shopNav }));

    it.each(['orders', 'pedidos', 'sales', 'PEDIDOS', 'ventas'])(
        'finds Orders with “%s”',
        async (query) => {
            const { input } = renderMenu();

            await userEvent.type(input, query);

            expect(optionLabels()[0]).toBe('Orders');
        },
    );

    it('shows the group path of each result', async () => {
        const { input } = renderMenu();

        await userEvent.type(input, 'pedidos');

        expect(
            screen.getByRole('option', { name: /Orders/ }),
        ).toHaveTextContent('OrdersTools › Shop');
    });

    it('finds children of core items too, without accents', async () => {
        const { input } = renderMenu();

        await userEvent.type(input, 'categorias');

        expect(optionLabels()).toEqual(['Categories']);
        expect(
            screen.getByRole('option', { name: /Categories/ }),
        ).toHaveTextContent('Content › Posts');
    });

    it('puts label matches before keyword matches', async () => {
        const { input } = renderMenu();

        await userEvent.type(input, 'sto');

        expect(optionLabels()).toEqual(['Shop', 'Products']);
    });

    it('needs every word to match', async () => {
        const { input } = renderMenu();

        await userEvent.type(input, 'shop stock');

        expect(optionLabels()).toEqual(['Products']);
    });

    it('never lists items the server did not send', async () => {
        setPageProps({
            versoNav: shopNav.filter((entry) => entry.key !== 'shop.orders'),
        });
        const { input } = renderMenu();

        await userEvent.type(input, 'pedidos');

        expect(optionLabels()).toEqual([]);
    });
});

describe('search helpers', () => {
    it('removes accents and case', () => {
        expect(normalize('  Categorías ')).toBe('categorias');
    });

    it('scores labels above keywords and paths', () => {
        const command = {
            key: 'shop.orders',
            label: 'Orders',
            section: 'Tools',
            path: ['Tools', 'Shop', 'Orders'],
            keywords: ['sales'],
            href: '/admin/shop/orders',
            icon: 'orders',
        };

        expect(matchScore(command, 'ord')).toBeGreaterThan(
            matchScore(command, 'sal'),
        );
        expect(matchScore(command, 'sal')).toBeGreaterThan(
            matchScore(command, 'tools'),
        );
        expect(matchScore(command, 'invoices')).toBe(0);
    });

    it('turns nested items into commands with their path', () => {
        const commands = toCommands([
            {
                key: 'system',
                label: 'System',
                href: '/admin/system/settings',
                icon: 'customize',
                group: 'administration',
            },
            {
                key: 'server-health',
                label: 'Server health',
                href: '/admin/system/health',
                icon: 'shield',
                group: 'administration',
                parent: 'system',
            },
        ]);

        expect(commands.map((command) => command.path)).toEqual([
            ['Administration', 'System'],
            ['Administration', 'System', 'Server health'],
        ]);
    });

    it('translates the section names in the path', () => {
        setMessages('es', { Administration: 'Administración' });

        const commands = toCommands([
            {
                key: 'system',
                label: 'Sistema',
                href: '/admin/system/settings',
                icon: 'customize',
                group: 'administration',
            },
        ]);

        expect(commands[0].path).toEqual(['Administración', 'Sistema']);
        expect(commands[0].section).toBe('Administración');
    });

    it('finds the translated profile by its English and translated words', () => {
        setMessages('es', {
            'Your profile': 'Tu perfil',
            Account: 'Cuenta',
            Password: 'Contraseña',
        });

        const [profileCommand] = extraCommands();

        expect(profileCommand.label).toBe('Tu perfil');
        expect(profileCommand.path).toEqual(['Cuenta', 'Tu perfil']);
        expect(matchScore(profileCommand, 'contraseña')).toBeGreaterThan(0);
        expect(matchScore(profileCommand, 'password')).toBeGreaterThan(0);
        expect(matchScore(profileCommand, 'account')).toBeGreaterThan(0);
        expect(matchScore(profileCommand, 'your profile')).toBeGreaterThan(0);
    });

    it('keeps the sidebar order for equal scores', () => {
        const commands = toCommands(nav);

        expect(
            searchCommands(commands, 'content').map((command) => command.label),
        ).toEqual(['Posts', 'Pages', 'Media']);
    });
});

describe('useCommandShortcut', () => {
    it.each([
        ['Cmd+K', { metaKey: true }],
        ['Ctrl+K', { ctrlKey: true }],
    ])('opens with %s', (_, modifier) => {
        const onOpen = vi.fn();
        renderHook(() => useCommandShortcut(onOpen));

        const event = new KeyboardEvent('keydown', {
            key: 'k',
            cancelable: true,
            ...modifier,
        });

        act(() => {
            window.dispatchEvent(event);
        });

        expect(onOpen).toHaveBeenCalledOnce();
        expect(event.defaultPrevented).toBe(true);
    });

    it('also works with caps lock on', () => {
        const onOpen = vi.fn();
        renderHook(() => useCommandShortcut(onOpen));

        fireEvent.keyDown(window, { key: 'K', metaKey: true });

        expect(onOpen).toHaveBeenCalledOnce();
    });

    it('ignores K without a modifier', () => {
        const onOpen = vi.fn();
        renderHook(() => useCommandShortcut(onOpen));

        fireEvent.keyDown(window, { key: 'k' });
        fireEvent.keyDown(window, { key: 'j', metaKey: true });

        expect(onOpen).not.toHaveBeenCalled();
    });

    it('stops listening after unmount', () => {
        const onOpen = vi.fn();
        const { unmount } = renderHook(() => useCommandShortcut(onOpen));

        unmount();
        fireEvent.keyDown(window, { key: 'k', metaKey: true });

        expect(onOpen).not.toHaveBeenCalled();
    });
});
