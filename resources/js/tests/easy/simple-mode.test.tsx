import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    DropdownMenu,
    DropdownMenuContent,
} from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import { SimpleModeSwitch } from '@/components/verso/easy/simple-mode-switch';
import {
    ENTRY_PLACEHOLDER,
    SimpleModeProfileSlot,
    editOnPageAction,
} from '@/components/verso/easy/slots';
import { SidebarNav } from '@/components/verso/shell/sidebar-nav';
import {
    leadsToMenuPage,
    simplePanels,
    simpleStats,
} from '@/lib/easy/simple-dashboard';
import Dashboard from '@/pages/admin/core/dashboard';
import type { DashboardPanel, DashboardStat, VersoNavItem } from '@/types';
import {
    resetPageProps,
    router,
    setPageProps,
    setPageUrl,
} from '@/tests/mocks/inertia';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

// The short menu the server sends in simple mode, with its plain names.
const simpleNav: VersoNavItem[] = [
    {
        key: 'dashboard',
        label: 'Dashboard',
        href: '/admin',
        icon: 'dashboard',
        group: 'dashboard',
    },
    {
        key: 'entries.post',
        label: 'Blog posts',
        href: '/admin/entries/post',
        icon: 'posts',
        group: 'content',
        keywords: ['Posts'],
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
        label: 'Photos and files',
        href: '/admin/media',
        icon: 'media',
        group: 'content',
        keywords: ['Media'],
    },
    {
        key: 'site.editor',
        label: 'Header and footer',
        href: '/admin/site-editor',
        icon: 'siteEditor',
        group: 'design',
        keywords: ['Site Editor'],
    },
    {
        key: 'easy-details',
        label: 'Your site details',
        href: '/admin/easy/details',
        icon: 'site',
        group: 'settings',
    },
];

const stats: DashboardStat[] = [
    {
        key: 'published.post',
        label: 'Published posts',
        value: 12,
        href: '/admin/entries/post',
        icon: 'posts',
    },
    {
        key: 'media',
        label: 'Media files',
        value: 48,
        href: '/admin/media',
        icon: 'media',
    },
    {
        key: 'users',
        label: 'Users',
        value: 3,
        href: '/admin/users',
        icon: 'users',
    },
    {
        key: 'team',
        label: 'Team members',
        value: 4,
        href: null,
        icon: 'users',
    },
];

const panels: DashboardPanel[] = [
    {
        key: 'recent-entries',
        label: 'Recent entries',
        icon: 'entries',
        emptyText: 'No entries yet.',
        href: '/admin/entries/post?sort=latest',
        items: [],
    },
    {
        key: 'pending-invitations',
        label: 'Pending invitations',
        icon: 'users',
        emptyText: 'Nobody is invited.',
        href: '/admin/users#invitations',
        items: [],
    },
];

const marta = {
    id: 7,
    name: 'Marta Vidal',
    email: 'marta@harborlane.example',
    role: 'admin',
};

beforeEach(() => router.put.mockClear());
afterEach(() => resetPageProps());

describe('SimpleModeSwitch', () => {
    it('is a labelled switch that saves the new state', async () => {
        render(<SimpleModeSwitch enabled={false} />);

        const toggle = screen.getByRole('switch', { name: 'Simple mode' });
        expect(toggle).not.toBeChecked();
        expect(toggle).toHaveAttribute('switch', '');
        expect(toggle).toHaveAccessibleDescription(
            /Show only the tools most people need/,
        );

        await userEvent.click(toggle);

        expect(router.put).toHaveBeenCalledWith(
            '/admin/easy/simple-mode',
            { enabled: true },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('turns simple mode off from the profile card', async () => {
        render(
            <SimpleModeProfileSlot
                item={{
                    key: 'easy.simple-mode',
                    component: 'easy.simple-mode',
                    label: null,
                    order: 10,
                    props: {},
                }}
                props={{ enabled: true, siteDefault: true }}
                context={{}}
            />,
        );

        expect(
            screen.getByRole('heading', { name: 'How the admin looks' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText('This site starts everyone in simple mode.'),
        ).toBeInTheDocument();

        await userEvent.click(
            screen.getByRole('switch', { name: 'Simple mode' }),
        );

        expect(router.put).toHaveBeenCalledWith(
            '/admin/easy/simple-mode',
            { enabled: false },
            expect.anything(),
        );
    });
});

describe('the account menu', () => {
    function renderMenu() {
        render(
            <DropdownMenu open>
                <DropdownMenuContent>
                    <UserMenuContent user={marta as never} />
                </DropdownMenuContent>
            </DropdownMenu>,
        );
    }

    it('shows the admin mode and switches to advanced mode', async () => {
        setPageProps({ simpleMode: true });
        renderMenu();

        const trigger = screen.getByRole('menuitem', { name: /Admin mode/ });
        expect(trigger).toHaveTextContent('Simple');

        await userEvent.click(trigger);
        const simple = await screen.findByRole('menuitemradio', {
            name: 'Simple mode',
        });
        expect(simple).toHaveAttribute('aria-checked', 'true');

        await userEvent.click(
            screen.getByRole('menuitemradio', { name: 'Advanced mode' }),
        );

        expect(router.put).toHaveBeenCalledWith(
            '/admin/easy/simple-mode',
            { enabled: false },
            expect.anything(),
        );
    });

    it('names advanced mode when simple mode is off', () => {
        setPageProps({ simpleMode: false });
        renderMenu();

        expect(
            screen.getByRole('menuitem', { name: /Admin mode/ }),
        ).toHaveTextContent('Advanced');
    });

    it('has no admin mode item outside the admin', () => {
        renderMenu();

        expect(
            screen.queryByRole('menuitem', { name: /Admin mode/ }),
        ).toBeNull();
    });
});

describe('the sidebar in simple mode', () => {
    it('shows the short menu with plain names', () => {
        setPageProps({
            versoNav: simpleNav,
            siteName: 'Harbor Lane Bakery',
            auth: { user: marta },
        });
        setPageUrl('/admin/site-editor');
        render(<SidebarNav />);

        const nav = screen.getByRole('navigation');
        expect(
            within(nav).getByRole('link', { name: 'Header and footer' }),
        ).toHaveAttribute('aria-current', 'page');
        expect(
            within(nav).getByRole('link', { name: 'Photos and files' }),
        ).toBeInTheDocument();
        expect(
            within(nav).getByRole('link', { name: 'Blog posts' }),
        ).toBeInTheDocument();
        expect(within(nav).queryByText('Site Editor')).toBeNull();
        expect(within(nav).queryByText('Plugins and themes')).toBeNull();
    });
});

describe('the dashboard in simple mode', () => {
    it('keeps only parts that lead to pages in the menu, with plain names', () => {
        expect(leadsToMenuPage(null, simpleNav)).toBe(true);
        expect(leadsToMenuPage('/admin/users', simpleNav)).toBe(false);
        expect(simpleStats(stats, simpleNav).map((stat) => stat.label)).toEqual(
            ['Published posts', 'Photos and files'],
        );
        expect(simplePanels(panels, simpleNav)).toEqual([
            expect.objectContaining({
                label: 'Latest pages and posts',
                emptyText:
                    'Nothing written yet. Your new pages and posts show here.',
            }),
        ]);
    });

    it('shows the simple dashboard and the launch checklist slot', () => {
        setPageProps({
            auth: { user: marta },
            simpleMode: true,
            versoNav: simpleNav,
            versoSlots: {
                'dashboard-top': [
                    {
                        key: 'easy.launch-checklist',
                        component: 'test.top-card',
                        label: null,
                        order: 10,
                        props: {},
                    },
                ],
            },
        });
        render(<Dashboard stats={stats} panels={panels} />);

        expect(screen.getByText('Photos and files')).toBeInTheDocument();
        expect(screen.getByText('Latest pages and posts')).toBeInTheDocument();
        expect(screen.queryByText('Users')).toBeNull();
        expect(screen.queryByText('Recent entries')).toBeNull();
        expect(screen.queryByText('Pending invitations')).toBeNull();
    });

    it('shows everything outside simple mode', () => {
        setPageProps({
            auth: { user: marta },
            simpleMode: false,
            versoNav: simpleNav,
        });
        render(<Dashboard stats={stats} panels={panels} />);

        expect(screen.getByText('Users')).toBeInTheDocument();
        expect(screen.getByText('Recent entries')).toBeInTheDocument();
    });
});

describe('"Edit on the page" in the page list', () => {
    const context = (update: boolean) => ({
        row: {
            id: 42,
            title: 'Our bakery',
            status: 'published',
            url: '/our-bakery',
            can: { update },
        },
        contentType: { name: 'page' } as never,
    });

    it('links to the page editor of the row', () => {
        expect(
            editOnPageAction(context(true), {
                href: `/admin/easy/pages/${ENTRY_PLACEHOLDER}`,
            }),
        ).toEqual({
            label: 'Edit on the page',
            href: '/admin/easy/pages/42',
        });
    });

    it('is left out for pages the person may not change', () => {
        expect(
            editOnPageAction(context(false), {
                href: `/admin/easy/pages/${ENTRY_PLACEHOLDER}`,
            }),
        ).toBeNull();
        expect(editOnPageAction(context(true), {})).toBeNull();
    });
});
