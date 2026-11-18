import {
    act,
    render,
    renderHook,
    screen,
    within,
} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    ColumnChooser,
    chooserColumns,
} from '@/components/verso/ui/column-chooser';
import { DataTable, shownColumns } from '@/components/verso/ui/data-table';
import type { DataTableColumn } from '@/components/verso/ui/data-table';
import { ListCell, listCellClass } from '@/components/verso/ui/list-cell';
import { defaultColumnsAt, useListView } from '@/components/verso/ui/list-view';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

type Post = {
    id: number;
    title: string;
    categories: string[];
    words: number;
    updated: string;
};

const posts: Post[] = [
    {
        id: 4,
        title: 'Ten markets in Valencia',
        categories: ['Food', 'Travel'],
        words: 1240,
        updated: '2026-11-12T09:30:00+00:00',
    },
    {
        id: 9,
        title: 'Slow mornings in Kyoto',
        categories: ['Travel'],
        words: 860,
        updated: '2026-11-14T18:05:00+00:00',
    },
];

const columns: DataTableColumn<Post>[] = [
    {
        key: 'title',
        header: 'Title',
        primary: true,
        cell: (post) => post.title,
    },
    {
        key: 'categories',
        header: 'Categories',
        label: 'Categories',
        optional: true,
        defaultFrom: 'lg',
        cell: (post) => post.categories.join(', '),
    },
    {
        key: 'words',
        header: 'Words',
        label: 'Words',
        optional: true,
        defaultFrom: '2xl',
        cell: (post) => post.words,
    },
    {
        key: 'hidden',
        header: 'Internal id',
        label: 'Internal id',
        optional: true,
        defaultFrom: 'never',
        cell: (post) => post.id,
    },
];

const headers = () =>
    within(screen.getByRole('table'))
        .getAllByRole('columnheader')
        .map((header) => header.textContent);

describe('shownColumns', () => {
    it('keeps every column and adds container query classes when the user picked nothing', () => {
        const shown = shownColumns(columns, null);

        expect(shown.map(({ column }) => column.key)).toEqual([
            'title',
            'categories',
            'words',
        ]);
        expect(shown[1].widthClass).toBe(
            'hidden @min-[64rem]/table:table-cell',
        );
        expect(shown[2].widthClass).toBe(
            'hidden @min-[100rem]/table:table-cell',
        );
        expect(shown[0].widthClass).toBe('');
    });

    it('shows exactly the columns the user picked', () => {
        expect(
            shownColumns(columns, ['words', 'hidden']).map(
                ({ column, widthClass }) => [column.key, widthClass],
            ),
        ).toEqual([
            ['title', ''],
            ['words', ''],
            ['hidden', ''],
        ]);
    });
});

describe('DataTable views', () => {
    it('is a size container, so wide lists show more columns', () => {
        const { container } = render(
            <DataTable
                rows={posts}
                columns={columns}
                rowKey={(post) => post.id}
            />,
        );

        expect(container.firstElementChild).toHaveClass('@container/table');
        expect(screen.getByRole('columnheader', { name: 'Words' })).toHaveClass(
            '@min-[100rem]/table:table-cell',
        );
    });

    it('renders the picked columns only', () => {
        render(
            <DataTable
                rows={posts}
                columns={columns}
                rowKey={(post) => post.id}
                visibleColumns={['words']}
            />,
        );

        expect(headers()).toEqual(['Title', 'Words']);
    });

    it('makes rows smaller in compact density', () => {
        const { container } = render(
            <DataTable
                rows={posts}
                columns={columns}
                rowKey={(post) => post.id}
                density="compact"
                visibleColumns={[]}
            />,
        );

        expect(container.firstElementChild).toHaveAttribute(
            'data-density',
            'compact',
        );
        expect(
            screen.getByRole('cell', { name: 'Ten markets in Valencia' }),
        ).toHaveClass('py-1');
    });

    it('keeps the sticky header', () => {
        render(
            <DataTable
                rows={posts}
                columns={columns}
                rowKey={(post) => post.id}
            />,
        );

        expect(screen.getByRole('columnheader', { name: 'Title' })).toHaveClass(
            'sticky',
            'top-14',
        );
    });
});

describe('defaultColumnsAt', () => {
    it('gives the columns a list width shows by default', () => {
        const chooser = chooserColumns(columns);

        expect(defaultColumnsAt(chooser, 900)).toEqual([]);
        expect(defaultColumnsAt(chooser, 1100)).toEqual(['categories']);
        expect(defaultColumnsAt(chooser, 3200)).toEqual([
            'categories',
            'words',
        ]);
    });
});

describe('ColumnChooser', () => {
    function renderChooser(visible: string[] | null, width = 1100) {
        const onChange = vi.fn();
        const onDensityChange = vi.fn();

        render(
            <ColumnChooser
                columns={chooserColumns(columns)}
                visible={visible}
                onChange={onChange}
                density="comfortable"
                onDensityChange={onDensityChange}
                listWidth={() => width}
            />,
        );

        return { onChange, onDensityChange };
    }

    it('starts from the columns the width shows and saves the new list', async () => {
        const { onChange } = renderChooser(null);

        await userEvent.click(screen.getByRole('button', { name: 'View' }));

        expect(
            screen.getByRole('menuitemcheckbox', { name: 'Categories' }),
        ).toHaveAttribute('aria-checked', 'true');
        expect(
            screen.getByRole('menuitemcheckbox', { name: 'Words' }),
        ).toHaveAttribute('aria-checked', 'false');

        await userEvent.click(
            screen.getByRole('menuitemcheckbox', { name: 'Words' }),
        );

        expect(onChange).toHaveBeenCalledWith(['categories', 'words']);
    });

    it('hides a picked column and goes back to the defaults', async () => {
        const { onChange } = renderChooser(['categories', 'words']);

        await userEvent.click(screen.getByRole('button', { name: 'View' }));
        await userEvent.click(
            screen.getByRole('menuitemcheckbox', { name: 'Categories' }),
        );

        expect(onChange).toHaveBeenLastCalledWith(['words']);

        await userEvent.click(
            screen.getByRole('menuitem', { name: 'Use the default columns' }),
        );

        expect(onChange).toHaveBeenLastCalledWith(null);
    });

    it('changes the density', async () => {
        const { onDensityChange } = renderChooser(null);

        await userEvent.click(screen.getByRole('button', { name: 'View' }));
        await userEvent.click(
            screen.getByRole('menuitemradio', { name: 'Compact' }),
        );

        expect(onDensityChange).toHaveBeenCalledWith('compact');
    });
});

describe('useListView', () => {
    const fetchMock = vi.fn();

    beforeEach(() => {
        fetchMock.mockResolvedValue(new Response(null, { status: 204 }));
        vi.stubGlobal('fetch', fetchMock);
        document.cookie = 'XSRF-TOKEN=harbor-token';
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        fetchMock.mockReset();
    });

    it('saves the columns and the density on the server', () => {
        const { result } = renderHook(() =>
            useListView({ key: 'entries.post', columns: null, density: null }),
        );

        expect(result.current.density).toBe('comfortable');

        act(() => result.current.setColumns(['word_count']));
        act(() => result.current.setDensity('compact'));

        expect(result.current.columns).toEqual(['word_count']);
        expect(fetchMock).toHaveBeenCalledTimes(2);

        const [url, init] = fetchMock.mock.calls[1];
        expect(url).toBe('/admin/list-views/entries.post');
        expect(init.method).toBe('PUT');
        expect(JSON.parse(init.body)).toEqual({
            columns: ['word_count'],
            density: 'compact',
        });
        expect(init.headers['X-XSRF-TOKEN']).toBe('harbor-token');
    });

    it('keeps the change when saving fails', async () => {
        fetchMock.mockRejectedValue(new Error('offline'));
        const { result } = renderHook(() =>
            useListView({
                key: 'comments',
                columns: ['website'],
                density: 'compact',
            }),
        );

        act(() => result.current.setColumns(null));
        await Promise.resolve();

        expect(result.current.columns).toBeNull();
    });
});

describe('ListCell', () => {
    it('shows tags, numbers, SEO and empty values', () => {
        render(
            <ul>
                <li data-testid="tags">
                    <ListCell kind="tags" value={['Food', 'Travel']} />
                </li>
                <li data-testid="number">
                    <ListCell kind="number" value={1240} />
                </li>
                <li data-testid="seo">
                    <ListCell kind="seo" value="partial" />
                </li>
                <li data-testid="empty">
                    <ListCell kind="tags" value={[]} />
                </li>
                <li data-testid="money">
                    <ListCell kind="money" value="€12.50" />
                </li>
            </ul>,
        );

        expect(screen.getByTestId('tags')).toHaveTextContent('Food, Travel');
        expect(screen.getByTestId('number')).toHaveTextContent(
            (1240).toLocaleString(),
        );
        expect(screen.getByTestId('seo')).toHaveTextContent('Partial');
        expect(screen.getByTestId('empty')).toHaveTextContent('None');
        expect(screen.getByTestId('money')).toHaveTextContent('€12.50');
    });

    it('shows a small image with its alt text', () => {
        render(
            <ListCell
                kind="image"
                value={{
                    src: '/img/2026/11/oranges.jpg?w=80&h=80',
                    alt: 'Oranges at the market',
                }}
            />,
        );

        expect(
            screen.getByRole('img', { name: 'Oranges at the market' }),
        ).toHaveAttribute('loading', 'lazy');
    });

    it('lines numbers up on the end side', () => {
        expect(listCellClass('number')).toContain('text-end');
        expect(listCellClass('money')).toContain('text-end');
    });
});
