import { describe, expect, it } from 'vitest';
import {
    activeNavKey,
    ancestorKeys,
    buildNavTree,
    groupNavItems,
    hasNewItem,
    navPath,
} from '@/components/verso/shell/nav-groups';
import type { VersoNavItem } from '@/types';

function item(
    key: string,
    href: string,
    group: VersoNavItem['group'],
): VersoNavItem {
    return { key, label: key, href, icon: key, group };
}

const nav: VersoNavItem[] = [
    item('settings', '/admin/settings', 'settings'),
    item('dashboard', '/admin', 'dashboard'),
    item('posts', '/admin/entries/post', 'content'),
    item('categories', '/admin/terms/category', 'content'),
    item('menus', '/admin/menus', 'design'),
    item('redirects', '/admin/redirects', 'design'),
];

describe('groupNavItems', () => {
    it('puts sections in sidebar order and keeps the server order inside', () => {
        expect(
            groupNavItems(nav).map((section) => [
                section.group,
                section.label,
                section.items.map((entry) => entry.key),
            ]),
        ).toEqual([
            ['dashboard', null, ['dashboard']],
            ['content', 'Content', ['posts', 'categories']],
            ['design', 'Design', ['menus', 'redirects']],
            ['settings', 'Settings', ['settings']],
        ]);
    });

    it('leaves out empty sections', () => {
        expect(groupNavItems([nav[2]]).map((section) => section.group)).toEqual(
            ['content'],
        );
    });

    it('puts items without a group in Content', () => {
        const loose = {
            ...item('newsletter', '/admin/plugins/newsletter', 'content'),
            group: undefined,
        } as unknown as VersoNavItem;

        expect(groupNavItems([loose])[0].group).toBe('content');
    });
});

describe('activeNavKey', () => {
    it('matches the exact path', () => {
        expect(activeNavKey(nav, '/admin/menus')).toBe('menus');
    });

    it('matches child pages', () => {
        expect(activeNavKey(nav, '/admin/entries/post/12/edit')).toBe('posts');
    });

    it('does not treat the dashboard as the parent of every page', () => {
        expect(activeNavKey(nav, '/admin/unknown-page')).toBeNull();
    });

    it('marks the dashboard only on its own path', () => {
        expect(activeNavKey(nav, '/admin')).toBe('dashboard');
        expect(activeNavKey(nav, '/admin/')).toBe('dashboard');
    });

    it('ignores the query string and the hash', () => {
        expect(activeNavKey(nav, '/admin/redirects?page=2#list')).toBe(
            'redirects',
        );
    });

    it('does not match a path that only starts with the same letters', () => {
        expect(activeNavKey(nav, '/admin/menus-archive')).toBeNull();
    });

    it('prefers the longest matching link', () => {
        const withChild = [
            ...nav,
            item('menu-locations', '/admin/menus/locations', 'design'),
        ];

        expect(activeNavKey(withChild, '/admin/menus/locations/footer')).toBe(
            'menu-locations',
        );
    });
});

// A sidebar like a real site with the Shop plugin: children point to their parent.
const shopNav: VersoNavItem[] = [
    { ...item('dashboard', '/admin', 'dashboard'), label: 'Dashboard' },
    {
        ...item('analytics.overview', '/admin/analytics', 'dashboard'),
        label: 'Analytics',
        parent: 'dashboard',
    },
    {
        ...item('entries.post', '/admin/entries/post', 'content'),
        label: 'Posts',
    },
    {
        ...item('terms.category', '/admin/terms/category', 'content'),
        label: 'Categories',
        parent: 'entries.post',
    },
    {
        ...item('plugin.shop', '/admin/shop/orders', 'tools'),
        label: 'Shop',
    },
    {
        ...item('shop.orders', '/admin/shop/orders', 'tools'),
        label: 'Orders',
        parent: 'plugin.shop',
        isNew: true,
    },
    {
        ...item('shop.products', '/admin/shop/products', 'tools'),
        label: 'Products',
        parent: 'plugin.shop',
    },
    { ...item('users', '/admin/users', 'administration'), label: 'Users' },
    {
        ...item(
            'users.invitations',
            '/admin/users#invitations',
            'administration',
        ),
        label: 'Invitations',
        parent: 'users',
    },
];

describe('buildNavTree', () => {
    it('puts children inside their parent', () => {
        const tree = buildNavTree(shopNav);

        expect(tree.map((node) => node.key)).toEqual([
            'dashboard',
            'entries.post',
            'plugin.shop',
            'users',
        ]);
        expect(tree[2].children.map((node) => node.key)).toEqual([
            'shop.orders',
            'shop.products',
        ]);
    });

    it('shows a child at the top when its parent is missing', () => {
        const tree = buildNavTree(
            shopNav.filter((entry) => entry.key !== 'plugin.shop'),
        );

        expect(tree.map((node) => node.key)).toContain('shop.orders');
    });

    it('splits the six groups in sidebar order', () => {
        expect(groupNavItems(shopNav).map((section) => section.label)).toEqual([
            null,
            'Content',
            'Tools',
            'Administration',
        ]);
    });
});

describe('activeNavKey with children', () => {
    it('picks the child when the parent links to the same page', () => {
        expect(activeNavKey(shopNav, '/admin/shop/orders')).toBe('shop.orders');
        expect(activeNavKey(shopNav, '/admin/shop/orders/41')).toBe(
            'shop.orders',
        );
    });

    it('never marks a page through a link to a part of it', () => {
        expect(activeNavKey(shopNav, '/admin/users')).toBe('users');
    });

    it('marks dashboard pages only on their own path', () => {
        expect(activeNavKey(shopNav, '/admin/analytics')).toBe(
            'analytics.overview',
        );
    });
});

describe('ancestorKeys and navPath', () => {
    it('lists the parents from the top', () => {
        expect(ancestorKeys(shopNav, 'shop.orders')).toEqual(['plugin.shop']);
        expect(ancestorKeys(shopNav, 'users')).toEqual([]);
    });

    it('gives the group path of an item', () => {
        expect(navPath(shopNav, 'shop.orders')).toEqual([
            'Tools',
            'Shop',
            'Orders',
        ]);
        expect(navPath(shopNav, 'terms.category')).toEqual([
            'Content',
            'Posts',
            'Categories',
        ]);
        expect(navPath(shopNav, 'analytics.overview')).toEqual([
            'Dashboard',
            'Analytics',
        ]);
        expect(navPath(shopNav, 'unknown')).toEqual([]);
    });
});

describe('hasNewItem', () => {
    it('is true when a child is new', () => {
        const tree = buildNavTree(shopNav);

        expect(hasNewItem(tree[2])).toBe(true);
        expect(hasNewItem(tree[1])).toBe(false);
    });
});
