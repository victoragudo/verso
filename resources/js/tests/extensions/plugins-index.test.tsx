import { render, screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import PluginsIndex from '@/pages/admin/plugins/index';
import type { InstalledTheme, PluginInfo } from '@/types';
import { resetPageProps } from '@/tests/mocks/inertia';
import { capabilityLabels } from './fixtures';

vi.mock('@inertiajs/react', async () => ({
    ...(await import('@/tests/mocks/inertia')).inertiaMock,
    // The upload form only needs its data and errors here.
    useForm: (data: Record<string, unknown>) => ({
        data,
        setData: () => undefined,
        errors: {},
        processing: false,
        post: () => undefined,
    }),
}));

afterEach(() => resetPageProps());

const shop: PluginInfo = {
    slug: 'shop',
    name: 'Shop',
    version: '1.0.0',
    description: 'Sell products with a cart, checkout and orders.',
    requires: '^0.1',
    permissions: ['manage_settings'],
    status: 'enabled',
    enabled: true,
    problems: [],
    removable: false,
    risk: null,
    location: {
        path: ['Tools', 'Shop'],
        key: 'plugin.shop',
        href: '/admin/shop/orders',
    },
};

const newsletter: PluginInfo = {
    ...shop,
    slug: 'newsletter',
    name: 'Newsletter',
    description: 'Subscribers, lists and campaigns.',
    status: 'disabled',
    enabled: false,
    location: {
        path: ['Tools', 'Newsletter'],
        key: 'plugin.newsletter',
        href: null,
    },
};

const harbor: InstalledTheme = {
    slug: 'harbor',
    name: 'Harbor',
    version: '2.1.0',
    description: 'A calm theme for a seaside bakery.',
    parentName: null,
    active: true,
    removable: false,
    risk: null,
};

function renderPage(tab: 'plugins' | 'themes' | 'add' | 'browse') {
    return render(
        <PluginsIndex
            tab={tab}
            tabs={['plugins', 'themes', 'add', 'browse']}
            plugins={[shop, newsletter]}
            themes={tab === 'themes' ? [harbor] : []}
            add={
                tab === 'add' || tab === 'browse'
                    ? {
                          kinds: ['plugin', 'theme'],
                          maxUploadKb: 20480,
                          verification: {
                              blockHighRisk: false,
                              canChange: true,
                              sandbox: false,
                          },
                      }
                    : null
            }
            registry={
                tab === 'browse'
                    ? { configured: false, error: null, entries: [] }
                    : null
            }
            capabilities={capabilityLabels}
            versoVersion="0.1.0"
        />,
    );
}

describe('Plugins and themes page', () => {
    it('shows the four tabs and marks the current one', () => {
        renderPage('plugins');

        const tabs = screen.getByRole('navigation', {
            name: 'Plugins and themes',
        });

        expect(
            within(tabs)
                .getAllByRole('link')
                .map((link) => link.textContent),
        ).toEqual([
            'Installed plugins2',
            'Installed themes',
            'Add new',
            'Browse',
        ]);
        expect(
            within(tabs).getByRole('link', { name: /Installed plugins/ }),
        ).toHaveAttribute('aria-current', 'page');
    });

    it('labels each plugin state in words and explains how to create a plugin when none exist', () => {
        const { unmount } = renderPage('plugins');

        expect(screen.getByText('Enabled')).toBeVisible();
        expect(screen.getByText('Disabled')).toBeVisible();
        unmount();

        render(
            <PluginsIndex
                tab="plugins"
                tabs={['plugins']}
                plugins={[]}
                themes={[]}
                add={null}
                registry={null}
                capabilities={capabilityLabels}
                versoVersion="0.1.0"
            />,
        );

        expect(screen.getByText('php artisan verso:make-plugin').tagName).toBe(
            'CODE',
        );
        expect(screen.getByText(/Create one with/).textContent).toBe(
            'Create one with php artisan verso:make-plugin.',
        );
    });

    it('tells where an enabled plugin is, with a link', () => {
        renderPage('plugins');

        expect(
            screen.getByRole('link', { name: 'Tools > Shop' }),
        ).toHaveAttribute('href', '/admin/shop/orders');
    });

    it('shows where a disabled plugin will be, without a link', () => {
        renderPage('plugins');

        expect(screen.getByText('Tools > Newsletter')).not.toHaveAttribute(
            'href',
        );
    });

    it('lists installed themes and sends people to Design, Themes to change them', () => {
        renderPage('themes');

        expect(screen.getByText('Harbor')).toBeVisible();
        expect(screen.getByText('Active')).toBeVisible();
        expect(screen.getByRole('link', { name: 'Customize' })).toHaveAttribute(
            'href',
            '/admin/themes',
        );
    });

    it('shows the upload form and plugin checks on the Add new tab', () => {
        renderPage('add');

        expect(screen.getByText('Upload a zip file')).toBeVisible();
        expect(screen.getByText('Plugin checks')).toBeVisible();
    });

    it('explains when no registry is set up on the Browse tab', () => {
        renderPage('browse');

        expect(screen.getByText('No registry is set up.')).toBeVisible();
    });
});
