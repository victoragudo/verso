import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { SidebarNav } from '@/components/verso/shell/sidebar-nav';
import { navStorageKey } from '@/components/verso/shell/use-nav-state';
import type { VersoNavItem } from '@/types';
import {
    resetPageProps,
    setPageProps,
    setPageUrl,
} from '@/tests/mocks/inertia';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

const nav: VersoNavItem[] = [
    {
        key: 'dashboard',
        label: 'Dashboard',
        href: '/admin',
        icon: 'dashboard',
        group: 'dashboard',
    },
    {
        key: 'analytics.overview',
        label: 'Analytics',
        href: '/admin/analytics',
        icon: 'analytics',
        group: 'dashboard',
        parent: 'dashboard',
        plugin: 'analytics',
    },
    {
        key: 'entries.post',
        label: 'Posts',
        href: '/admin/entries/post',
        icon: 'posts',
        group: 'content',
    },
    {
        key: 'terms.category',
        label: 'Categories',
        href: '/admin/terms/category',
        icon: 'categories',
        group: 'content',
        parent: 'entries.post',
    },
    {
        key: 'terms.tag',
        label: 'Tags',
        href: '/admin/terms/tag',
        icon: 'tags',
        group: 'content',
        parent: 'entries.post',
    },
    {
        key: 'media',
        label: 'Media',
        href: '/admin/media',
        icon: 'media',
        group: 'content',
    },
    {
        key: 'site.menus',
        label: 'Menus',
        href: '/admin/site/menus',
        icon: 'menus',
        group: 'design',
    },
    {
        key: 'plugin.shop',
        label: 'Shop',
        href: '/admin/shop/orders',
        icon: 'shop',
        group: 'tools',
        plugin: 'shop',
    },
    {
        key: 'shop.orders',
        label: 'Orders',
        href: '/admin/shop/orders',
        icon: 'orders',
        group: 'tools',
        parent: 'plugin.shop',
        plugin: 'shop',
        isNew: true,
    },
    {
        key: 'shop.products',
        label: 'Products',
        href: '/admin/shop/products',
        icon: 'product',
        group: 'tools',
        parent: 'plugin.shop',
        plugin: 'shop',
    },
    {
        key: 'users',
        label: 'Users',
        href: '/admin/users',
        icon: 'users',
        group: 'administration',
    },
    {
        key: 'site.settings',
        label: 'General',
        href: '/admin/site/settings',
        icon: 'settings',
        group: 'settings',
    },
];

const storageKey = navStorageKey(42);

function renderSidebar(url = '/admin', highlightKey: string | null = null) {
    setPageUrl(url);
    setPageProps({
        versoNav: nav,
        auth: { user: { id: 42, name: 'Carmen Vidal' } },
    });

    return render(<SidebarNav highlightKey={highlightKey} />);
}

const section = (label: string) =>
    screen.getByRole('button', { name: new RegExp(`^${label}`) });

beforeEach(() => window.localStorage.clear());

afterEach(() => resetPageProps());

describe('SidebarNav groups', () => {
    it('shows the six groups in order, with the dashboard first and no heading', () => {
        renderSidebar();

        const headings = screen
            .getAllByRole('button', { expanded: true })
            .filter(
                (button) =>
                    button.textContent && !button.getAttribute('aria-label'),
            )
            .map((button) => button.textContent);

        expect(headings).toEqual([
            'Content',
            'Design',
            'Tools',
            'Administration',
            'Settings',
        ]);
        expect(screen.getByRole('link', { name: 'Dashboard' })).toBeVisible();
    });

    it('closes and opens a group and remembers it per user', async () => {
        renderSidebar();

        await userEvent.click(section('Design'));

        expect(section('Design')).toHaveAttribute('aria-expanded', 'false');
        expect(screen.queryByRole('link', { name: 'Menus' })).toBeNull();
        expect(
            JSON.parse(window.localStorage.getItem(storageKey)!).closedGroups,
        ).toEqual(['design']);
    });

    it('reads the saved state when it renders again', () => {
        window.localStorage.setItem(
            storageKey,
            JSON.stringify({
                closedGroups: ['settings'],
                openItems: ['plugin.shop'],
            }),
        );

        renderSidebar();

        expect(section('Settings')).toHaveAttribute('aria-expanded', 'false');
        expect(screen.getByRole('link', { name: /^Orders/ })).toBeVisible();
    });

    it('works when storage is blocked', async () => {
        const getItem = vi
            .spyOn(Storage.prototype, 'getItem')
            .mockImplementation(() => {
                throw new Error('blocked');
            });
        const setItem = vi
            .spyOn(Storage.prototype, 'setItem')
            .mockImplementation(() => {
                throw new Error('blocked');
            });

        renderSidebar();
        await userEvent.click(section('Design'));

        expect(section('Design')).toHaveAttribute('aria-expanded', 'false');

        getItem.mockRestore();
        setItem.mockRestore();
    });

    it('always opens the group of the current page', () => {
        window.localStorage.setItem(
            storageKey,
            JSON.stringify({ closedGroups: ['design'], openItems: [] }),
        );

        renderSidebar('/admin/site/menus');

        expect(section('Design')).toHaveAttribute('aria-expanded', 'true');
        expect(screen.getByRole('link', { name: 'Menus' })).toHaveAttribute(
            'aria-current',
            'page',
        );
    });
});

describe('SidebarNav items with children', () => {
    it('keeps children closed until the user opens them', async () => {
        renderSidebar();

        expect(screen.queryByRole('link', { name: 'Categories' })).toBeNull();

        await userEvent.click(
            screen.getByRole('button', { name: 'Show pages in Posts' }),
        );

        expect(screen.getByRole('link', { name: 'Categories' })).toBeVisible();
        expect(
            screen.getByRole('button', { name: 'Hide pages in Posts' }),
        ).toHaveAttribute('aria-expanded', 'true');
    });

    it('opens the parent of the current page and marks the child', () => {
        renderSidebar('/admin/terms/tag');

        expect(screen.getByRole('link', { name: 'Tags' })).toHaveAttribute(
            'aria-current',
            'page',
        );
        expect(screen.getByRole('link', { name: 'Posts' })).not.toHaveAttribute(
            'aria-current',
        );
    });

    it('marks the child, not the plugin item, when both link to the same page', () => {
        renderSidebar('/admin/shop/orders');

        expect(screen.getByRole('link', { name: /^Orders/ })).toHaveAttribute(
            'aria-current',
            'page',
        );
        expect(screen.getByRole('link', { name: /^Shop/ })).not.toHaveAttribute(
            'aria-current',
        );
    });

    it('shows dashboard pages as dashboard tabs, not in the sidebar', () => {
        renderSidebar();

        expect(screen.queryByRole('link', { name: 'Analytics' })).toBeNull();
        expect(
            screen.queryByRole('button', { name: /pages in Dashboard/ }),
        ).toBeNull();
    });
});

describe('SidebarNav New badge', () => {
    it('shows New on items the user has not opened and on their closed parent', () => {
        renderSidebar();

        expect(
            within(screen.getByRole('link', { name: /^Shop/ })).getByText(
                'New',
            ),
        ).toBeVisible();
    });

    it('shows New next to a closed group that holds a new item', async () => {
        renderSidebar();

        await userEvent.click(section('Tools'));

        expect(section('Tools')).toHaveTextContent('New');
    });
});

describe('SidebarNav highlight', () => {
    it('opens the path to a highlighted item and marks it', () => {
        window.localStorage.setItem(
            storageKey,
            JSON.stringify({ closedGroups: ['tools'], openItems: [] }),
        );

        const { container } = renderSidebar('/admin/plugins', 'plugin.shop');

        expect(section('Tools')).toHaveAttribute('aria-expanded', 'true');
        expect(
            container.querySelector('[data-nav-key="plugin.shop"]'),
        ).toHaveAttribute('data-highlighted', 'true');
        expect(screen.getByRole('link', { name: /^Orders/ })).toBeVisible();
    });
});
