import { fireEvent, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import MenusIndex from '@/pages/admin/site/menus';
import { resetPageProps, router, setPageProps } from '@/tests/mocks/inertia';
import type { MenuItemNode } from '@/types';
import { pickOption } from '@/tests/pick-option';

vi.mock('@inertiajs/react', async () => {
    const { inertiaMock } = await import('@/tests/mocks/inertia');

    // A small useForm stand-in for the menu settings form.
    function useForm<T extends Record<string, unknown>>(initial: T) {
        return {
            data: initial,
            setData: vi.fn(),
            errors: {},
            processing: false,
            transform: vi.fn(),
            put: vi.fn(),
            post: vi.fn(),
        };
    }

    return { ...inertiaMock, useForm };
});

function link(
    title: string,
    url: string,
    children: MenuItemNode[] = [],
): MenuItemNode {
    return {
        id: null,
        type: 'custom',
        entry_id: null,
        term_id: null,
        label: title,
        url,
        open_in_new_tab: false,
        mega: false,
        rel: [],
        visibility: 'everyone',
        title,
        visible: true,
        children,
    };
}

function renderBuilder() {
    render(
        <MenusIndex
            menus={[
                {
                    id: 4,
                    name: 'Main navigation',
                    location: 'primary',
                    items_count: 3,
                },
            ]}
            menu={{
                id: 4,
                name: 'Main navigation',
                location: 'primary',
                items: [
                    link('Breads', '/breads'),
                    link('Cakes', '/cakes'),
                    link('Visit us', '/visit'),
                ],
            }}
            locations={[{ name: 'primary', label: 'Primary menu' }]}
            sources={{ entries: [], terms: [] }}
            maxDepth={3}
            relWords={['nofollow', 'sponsored', 'ugc', 'noreferrer']}
            visibilities={[
                { value: 'everyone', label: 'Everyone' },
                { value: 'members', label: 'Signed in members only' },
                { value: 'guests', label: 'Visitors who are not signed in' },
            ]}
        />,
    );
}

// Gives every row a 40px tall box, stacked, so the drop spot can be found from the midpoints.
function stackRows() {
    document
        .querySelectorAll<HTMLElement>('[data-menu-row]')
        .forEach((row, index) => {
            row.getBoundingClientRect = () => ({
                top: index * 50,
                height: 40,
                bottom: index * 50 + 40,
                left: 0,
                right: 300,
                width: 300,
                x: 0,
                y: index * 50,
                toJSON: () => ({}),
            });
        });
}

function savedItems(): Record<string, unknown>[] {
    fireEvent.click(screen.getByRole('button', { name: 'Save items' }));

    return (
        vi.mocked(router.put).mock.calls.at(-1)?.[1] as {
            items: Record<string, unknown>[];
        }
    ).items;
}

beforeEach(() => {
    setPageProps({ errors: {} });
    vi.mocked(router.put).mockClear();
    HTMLElement.prototype.setPointerCapture = vi.fn();
});

afterEach(() => resetPageProps());

describe('menu builder', () => {
    it('moves an item by dragging its handle and announces it', () => {
        renderBuilder();
        stackRows();
        const handle = screen.getByRole('button', { name: /Drag Visit us/ });

        fireEvent.pointerDown(handle, {
            pointerId: 1,
            button: 0,
            clientY: 120,
        });
        fireEvent.pointerMove(handle, { pointerId: 1, clientY: 10 });

        expect(document.querySelector('[data-menu-row]')?.className).toContain(
            'before:bg-primary',
        );

        fireEvent.pointerUp(handle, { pointerId: 1, clientY: 10 });

        expect(screen.getByRole('status')).toHaveTextContent(
            'Moved Visit us to position 1 of 3.',
        );
        expect(savedItems().map((item) => item.label)).toEqual([
            'Visit us',
            'Breads',
            'Cakes',
        ]);
    });

    it('ignores a second pointer and cancels with Escape', () => {
        renderBuilder();
        stackRows();
        const handle = screen.getByRole('button', { name: /Drag Visit us/ });

        fireEvent.pointerDown(handle, {
            pointerId: 1,
            button: 0,
            clientY: 120,
        });
        fireEvent.pointerMove(handle, { pointerId: 2, clientY: 10 });
        expect(
            document.querySelector('[data-menu-row]')?.className,
        ).not.toContain('before:bg-primary');

        fireEvent.pointerMove(handle, { pointerId: 1, clientY: 10 });
        fireEvent.keyDown(handle, { key: 'Escape' });
        fireEvent.pointerUp(handle, { pointerId: 1, clientY: 10 });

        expect(savedItems().map((item) => item.label)).toEqual([
            'Breads',
            'Cakes',
            'Visit us',
        ]);
    });

    it('saves the mega flag, rel words and who sees an item', async () => {
        renderBuilder();

        await userEvent.click(
            screen.getByRole('button', { name: 'Edit Breads' }),
        );
        const row = screen
            .getAllByRole('checkbox', { name: /mega menu/ })[0]
            .closest('[data-menu-row]') as HTMLElement;

        await userEvent.click(
            within(row).getByRole('checkbox', { name: /mega menu/ }),
        );
        await userEvent.click(
            within(row).getByRole('checkbox', { name: 'sponsored' }),
        );
        await pickOption(
            within(row).getByRole('combobox', { name: 'Who sees it' }),
            'Signed in members only',
        );

        expect(savedItems()[0]).toMatchObject({
            label: 'Breads',
            mega: true,
            rel: ['sponsored'],
            visibility: 'members',
        });
    });
});
