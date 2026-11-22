import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    ADMIN_CONTENT_ID,
    AdminShell,
} from '@/components/verso/shell/admin-shell';
import { resetPageProps, setPageProps } from '@/tests/mocks/inertia';

vi.mock('@inertiajs/react', async () => {
    const { inertiaMock, router } = await import('@/tests/mocks/inertia');

    return {
        ...inertiaMock,
        router: { ...router, on: () => () => undefined },
    };
});

afterEach(() => resetPageProps());

function renderShell() {
    setPageProps({
        auth: {
            user: {
                id: 7,
                name: 'Marta Oliveira',
                email: 'marta@harborlane.pt',
                role: 'admin',
            },
        },
        versoNav: [
            {
                key: 'dashboard',
                label: 'Dashboard',
                href: '/admin',
                icon: 'dashboard',
                group: 'dashboard',
            },
            {
                key: 'posts',
                label: 'Posts',
                href: '/admin/entries/post',
                icon: 'entries',
                group: 'content',
            },
        ],
    });

    return render(
        <AdminShell breadcrumbs={[{ title: 'Dashboard', href: '/admin' }]}>
            <h1>Dashboard</h1>
        </AdminShell>,
    );
}

describe('AdminShell skip link', () => {
    it('is the first focusable element on the page', async () => {
        const user = userEvent.setup();
        renderShell();

        await user.tab();

        expect(
            screen.getByRole('link', { name: 'Skip to content' }),
        ).toHaveFocus();
    });

    it('points at the main landmark, which can take focus', () => {
        renderShell();

        const link = screen.getByRole('link', { name: 'Skip to content' });
        const main = screen.getByRole('main');

        expect(link).toHaveAttribute('href', `#${ADMIN_CONTENT_ID}`);
        expect(main).toHaveAttribute('id', ADMIN_CONTENT_ID);
        expect(main).toHaveAttribute('tabindex', '-1');
        expect(main).toContainElement(
            screen.getByRole('heading', { name: 'Dashboard', level: 1 }),
        );
    });

    it('comes before the admin navigation', () => {
        renderShell();

        const link = screen.getByRole('link', { name: 'Skip to content' });
        const nav = screen.getByRole('navigation', { name: 'Admin' });

        expect(
            link.compareDocumentPosition(nav) &
                Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();
    });
});

describe('AdminShell overlays', () => {
    it('returns focus to the menu button when the navigation sheet closes', async () => {
        const user = userEvent.setup();
        renderShell();

        const trigger = screen.getByRole('button', { name: 'Open navigation' });
        await user.click(trigger);
        expect(await screen.findByRole('dialog')).toBeInTheDocument();

        await user.keyboard('{Escape}');

        expect(trigger).toHaveFocus();
    });

    it('returns focus to the search button when the command menu closes', async () => {
        const user = userEvent.setup();
        renderShell();

        const trigger = screen.getByRole('button', {
            name: /^Search pages/,
        });
        await user.click(trigger);
        expect(await screen.findByRole('dialog')).toBeInTheDocument();

        await user.keyboard('{Escape}');

        expect(trigger).toHaveFocus();
    });
});

describe('AdminShell content area', () => {
    it('uses the full width and is a size container for the page', () => {
        renderShell();

        const main = document.getElementById(ADMIN_CONTENT_ID);

        expect(main).toHaveClass('@container/page', 'w-full', 'admin-gutter');
        expect(main?.className).not.toMatch(/max-w-/);
    });
});

describe('AdminShell sidebar width', () => {
    afterEach(() => window.localStorage.clear());

    function rootWidth(): string {
        return (
            document
                .querySelector<HTMLElement>('[data-slot="admin-sidebar"]')
                ?.parentElement?.style.getPropertyValue('--admin-nav-width') ??
            ''
        );
    }

    it('starts wider than before and grows with the arrow keys', async () => {
        renderShell();
        const user = userEvent.setup();
        const handle = screen.getByRole('separator', {
            name: /Resize the menu/,
        });

        expect(rootWidth()).toBe('288px');
        expect(handle).toHaveAttribute('aria-valuenow', '288');

        handle.focus();
        await user.keyboard('{ArrowRight}');

        expect(rootWidth()).toBe('304px');
        expect(window.localStorage.getItem('verso.admin.nav-width')).toBe(
            '304',
        );
    });

    it('remembers the saved width and resets it on double click', async () => {
        window.localStorage.setItem('verso.admin.nav-width', '400');
        renderShell();
        const user = userEvent.setup();

        expect(rootWidth()).toBe('400px');

        await user.dblClick(
            screen.getByRole('separator', { name: /Resize the menu/ }),
        );

        expect(rootWidth()).toBe('288px');
    });
});
