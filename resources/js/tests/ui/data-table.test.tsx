import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { DataTable, sortByKey } from '@/components/verso/ui/data-table';
import type { DataTableColumn } from '@/components/verso/ui/data-table';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

type Post = {
    id: number;
    title: string;
    author: string;
    status: string;
    words: number;
};

const posts: Post[] = [
    {
        id: 12,
        title: 'How we bake our rye bread',
        author: 'Lucía Romero',
        status: 'Published',
        words: 1240,
    },
    {
        id: 15,
        title: 'Opening a second shop in Porto',
        author: 'Tomás Ferreira',
        status: 'Draft',
        words: 860,
    },
    {
        id: 18,
        title: 'Our winter menu',
        author: 'Lucía Romero',
        status: 'Scheduled',
        words: 410,
    },
];

const columns: DataTableColumn<Post>[] = [
    { key: 'title', header: 'Title', cell: (post) => post.title },
    { key: 'author', header: 'Author', cell: (post) => post.author },
    {
        key: 'status',
        header: 'Status',
        cell: (post) => post.status,
        wrap: true,
    },
    {
        key: 'words',
        header: 'Words',
        cell: (post) => post.words,
        hideOnMobile: true,
    },
];

// The stacked card list that replaces the table under md.
function stackedList(container: HTMLElement): HTMLElement {
    return container.querySelector('ul') as HTMLElement;
}

describe('DataTable', () => {
    it('renders a table with one row per item', () => {
        render(
            <DataTable rows={posts} columns={columns} rowKey={(p) => p.id} />,
        );

        const table = screen.getByRole('table');

        expect(within(table).getAllByRole('columnheader')).toHaveLength(4);
        expect(within(table).getAllByRole('row')).toHaveLength(4);
        expect(
            within(table).getByRole('cell', {
                name: 'Opening a second shop in Porto',
            }),
        ).toBeInTheDocument();
    });

    it('shows the table only when the list is at least 40rem wide', () => {
        render(
            <DataTable rows={posts} columns={columns} rowKey={(p) => p.id} />,
        );

        expect(screen.getByRole('table')).toHaveClass(
            'hidden',
            '@min-[40rem]/table:table',
        );
        expect(screen.getByRole('table').parentElement).toHaveClass(
            '@container/table',
        );
    });

    it('gives every header cell a column scope', () => {
        render(
            <DataTable
                rows={posts}
                columns={columns}
                rowKey={(p) => p.id}
                rowActions={() => [{ label: 'Edit', href: '/admin' }]}
                selection={{
                    selected: [],
                    onChange: () => undefined,
                    label: (post) => `Select ${post.title}`,
                }}
            />,
        );

        const headers = within(screen.getByRole('table')).getAllByRole(
            'columnheader',
        );

        expect(headers).toHaveLength(6);
        headers.forEach((header) =>
            expect(header).toHaveAttribute('scope', 'col'),
        );
    });

    it('adds a hidden caption for screen readers', () => {
        render(
            <DataTable
                rows={posts}
                columns={columns}
                rowKey={(p) => p.id}
                caption="Blog posts"
            />,
        );

        expect(screen.getByRole('table', { name: 'Blog posts' })).toBeVisible();
    });

    it('shows the empty slot when there are no rows', () => {
        render(
            <DataTable
                rows={[]}
                columns={columns}
                rowKey={(p: Post) => p.id}
                empty={<p>No posts match this filter.</p>}
            />,
        );

        expect(
            screen.getByText('No posts match this filter.'),
        ).toBeInTheDocument();
        expect(screen.queryByRole('table')).not.toBeInTheDocument();
    });

    it('renders an empty table when there are no rows and no empty slot', () => {
        render(
            <DataTable
                rows={[]}
                columns={columns}
                rowKey={(p: Post) => p.id}
            />,
        );

        expect(
            within(screen.getByRole('table')).getAllByRole('row'),
        ).toHaveLength(1);
    });

    it('applies column classes to header and cells', () => {
        render(
            <DataTable
                rows={posts.slice(0, 1)}
                columns={[
                    { ...columns[0] },
                    {
                        key: 'words',
                        header: 'Words',
                        cell: (post: Post) => post.words,
                        className: 'text-right',
                    },
                ]}
                rowKey={(p) => p.id}
            />,
        );

        expect(screen.getByRole('columnheader', { name: 'Words' })).toHaveClass(
            'text-right',
        );
        expect(screen.getByRole('cell', { name: '1240' })).toHaveClass(
            'text-right',
        );
    });
});

describe('DataTable stacked mode', () => {
    it('shows one card per row while the list is narrower than 40rem', () => {
        const { container } = render(
            <DataTable rows={posts} columns={columns} rowKey={(p) => p.id} />,
        );

        const list = stackedList(container);

        expect(list).toHaveClass('@min-[40rem]/table:hidden');
        expect(within(list).getAllByRole('listitem')).toHaveLength(3);
    });

    it('uses the first column as the card title by default', () => {
        const { container } = render(
            <DataTable rows={posts} columns={columns} rowKey={(p) => p.id} />,
        );

        const first = within(stackedList(container)).getAllByRole(
            'listitem',
        )[0];

        expect(first.querySelector('.font-medium')).toHaveTextContent(
            'How we bake our rye bread',
        );
    });

    it('uses the primary column as the card title', () => {
        const withPrimary = columns.map((column) =>
            column.key === 'author' ? { ...column, primary: true } : column,
        );
        const { container } = render(
            <DataTable
                rows={posts}
                columns={withPrimary}
                rowKey={(p) => p.id}
            />,
        );

        const card = within(stackedList(container)).getAllByRole('listitem')[1];

        expect(card.querySelector('.font-medium')).toHaveTextContent(
            'Tomás Ferreira',
        );
        expect(
            within(card)
                .getAllByRole('term')
                .map((term) => term.textContent),
        ).toEqual(['Title', 'Status']);
    });

    it('lists the other columns as label and value pairs', () => {
        const { container } = render(
            <DataTable rows={posts} columns={columns} rowKey={(p) => p.id} />,
        );

        const card = within(stackedList(container)).getAllByRole('listitem')[0];

        expect(
            within(card)
                .getAllByRole('term')
                .map((term) => term.textContent),
        ).toEqual(['Author', 'Status']);
        expect(
            within(card)
                .getAllByRole('definition')
                .map((value) => value.textContent),
        ).toEqual(['Lucía Romero', 'Published']);
    });

    it('leaves out columns hidden on mobile', () => {
        const { container } = render(
            <DataTable rows={posts} columns={columns} rowKey={(p) => p.id} />,
        );

        expect(
            within(stackedList(container)).queryByText('Words'),
        ).not.toBeInTheDocument();
        expect(
            within(stackedList(container)).queryByText('1240'),
        ).not.toBeInTheDocument();
    });

    it('truncates values unless the column wraps', () => {
        const { container } = render(
            <DataTable rows={posts} columns={columns} rowKey={(p) => p.id} />,
        );

        const card = within(stackedList(container)).getAllByRole('listitem')[0];
        const [author, status] = within(card).getAllByRole('definition');

        expect(author).toHaveClass('truncate');
        expect(status).not.toHaveClass('truncate');
    });

    it('leaves out the value list when only the title column exists', () => {
        const { container } = render(
            <DataTable
                rows={posts}
                columns={[columns[0]]}
                rowKey={(p) => p.id}
            />,
        );

        expect(stackedList(container).querySelector('dl')).toBeNull();
    });

    it('shows a row menu on each card', () => {
        const { container } = render(
            <DataTable
                rows={posts}
                columns={columns}
                rowKey={(p) => p.id}
                rowActions={(post) => [
                    { label: 'Edit', href: `/admin/entries/post/${post.id}` },
                ]}
            />,
        );

        expect(
            within(stackedList(container)).getAllByRole('button', {
                name: 'Row actions',
            }),
        ).toHaveLength(3);
    });

    it('opens the row actions of one card', async () => {
        const onSelect = vi.fn();
        const { container } = render(
            <DataTable
                rows={posts}
                columns={columns}
                rowKey={(p) => p.id}
                rowActions={(post) => [
                    {
                        label: 'Edit',
                        href: `/admin/entries/post/${post.id}/edit`,
                    },
                    { label: 'Copy link', onSelect },
                    {
                        label: 'Move to trash',
                        href: `/admin/entries/post/${post.id}`,
                        method: 'delete',
                        destructive: true,
                    },
                ]}
            />,
        );

        await userEvent.click(
            within(stackedList(container)).getAllByRole('button', {
                name: 'Row actions',
            })[1],
        );

        expect(
            await screen.findByRole('menuitem', { name: 'Edit' }),
        ).toHaveAttribute('href', '/admin/entries/post/15/edit');
        expect(
            screen.getByRole('menuitem', { name: 'Move to trash' }),
        ).toHaveAttribute('data-method', 'delete');

        await userEvent.click(
            screen.getByRole('menuitem', { name: 'Copy link' }),
        );

        expect(onSelect).toHaveBeenCalledOnce();
    });
});

// Keeps the selected keys in state like a list page does.
function SelectablePosts({
    onChange,
}: {
    onChange: (selected: number[]) => void;
}) {
    const [selected, setSelected] = useState<number[]>([15]);

    return (
        <DataTable
            rows={posts}
            columns={columns}
            rowKey={(p) => p.id}
            selection={{
                selected,
                onChange: (next) => {
                    setSelected(next);
                    onChange(next);
                },
                label: (post) => `Select ${post.title}`,
            }}
        />
    );
}

describe('DataTable selection', () => {
    it('checks the selected rows in the table and on the cards', () => {
        render(<SelectablePosts onChange={vi.fn()} />);

        const boxes = screen.getAllByRole('checkbox', {
            name: 'Select Opening a second shop in Porto',
        });

        expect(boxes).toHaveLength(2);
        boxes.forEach((box) => expect(box).toBeChecked());
    });

    it('highlights the selected card', () => {
        const { container } = render(<SelectablePosts onChange={vi.fn()} />);

        const cards = within(stackedList(container)).getAllByRole('listitem');

        expect(cards[1]).toHaveClass('bg-brand-soft/60');
        expect(cards[0]).not.toHaveClass('bg-brand-soft/60');
    });

    it('selects a row from its card', async () => {
        const onChange = vi.fn();
        const { container } = render(<SelectablePosts onChange={onChange} />);

        await userEvent.click(
            within(stackedList(container)).getByRole('checkbox', {
                name: 'Select Our winter menu',
            }),
        );

        expect(onChange).toHaveBeenLastCalledWith([15, 18]);
    });

    it('unselects a row from its card', async () => {
        const onChange = vi.fn();
        const { container } = render(<SelectablePosts onChange={onChange} />);

        await userEvent.click(
            within(stackedList(container)).getByRole('checkbox', {
                name: 'Select Opening a second shop in Porto',
            }),
        );

        expect(onChange).toHaveBeenLastCalledWith([]);
    });

    it('selects every row with the header checkbox', async () => {
        const onChange = vi.fn();
        render(<SelectablePosts onChange={onChange} />);

        await userEvent.click(
            screen.getByRole('checkbox', { name: 'Select all rows' }),
        );

        expect(onChange).toHaveBeenLastCalledWith([12, 15, 18]);
        expect(
            screen.getByRole('checkbox', { name: 'Select all rows' }),
        ).toBeChecked();
    });

    it('clears the selection with the header checkbox', async () => {
        const onChange = vi.fn();
        render(<SelectablePosts onChange={onChange} />);

        const all = screen.getByRole('checkbox', { name: 'Select all rows' });

        await userEvent.click(all);
        await userEvent.click(all);

        expect(onChange).toHaveBeenLastCalledWith([]);
    });
});

describe('DataTable row labels', () => {
    const editAction = (post: Post) => [
        { label: 'Edit', href: `/admin/entries/post/${post.id}/edit` },
    ];

    it('names each row menu after its row in the table and on the cards', () => {
        render(
            <DataTable
                rows={posts}
                columns={columns}
                rowKey={(p) => p.id}
                rowActions={editAction}
                rowLabel={(post) => post.title}
            />,
        );

        expect(
            screen.getAllByRole('button', {
                name: 'Actions for Opening a second shop in Porto',
            }),
        ).toHaveLength(2);
        expect(
            screen.queryByRole('button', { name: 'Row actions' }),
        ).not.toBeInTheDocument();
    });

    it('gives every row menu a different name', () => {
        const { container } = render(
            <DataTable
                rows={posts}
                columns={columns}
                rowKey={(p) => p.id}
                rowActions={editAction}
                rowLabel={(post) => post.title}
            />,
        );

        const names = within(container.querySelector('table')!)
            .getAllByRole('button')
            .map((button) => button.getAttribute('aria-label'));

        expect(new Set(names).size).toBe(posts.length);
    });

    it('keeps the generic name when no row label is given', () => {
        render(
            <DataTable
                rows={posts}
                columns={columns}
                rowKey={(p) => p.id}
                rowActions={editAction}
            />,
        );

        expect(
            screen.getAllByRole('button', { name: 'Row actions' }),
        ).toHaveLength(6);
    });
});

describe('DataTable sorting', () => {
    const sortable = columns.map((column) =>
        column.key === 'title' || column.key === 'words'
            ? { ...column, sortable: true }
            : column,
    );

    it('puts a button in sortable headers and marks only the sorted one', () => {
        render(
            <DataTable
                rows={posts}
                columns={sortable}
                rowKey={(p) => p.id}
                sort={{
                    column: 'words',
                    direction: 'descending',
                    onSort: () => undefined,
                }}
            />,
        );

        const table = screen.getByRole('table');
        const words = within(table).getByRole('columnheader', {
            name: 'Words',
        });
        const title = within(table).getByRole('columnheader', {
            name: 'Title',
        });

        expect(words).toHaveAttribute('aria-sort', 'descending');
        expect(title).not.toHaveAttribute('aria-sort');
        expect(within(words).getByRole('button', { name: 'Words' })).toBe(
            words.querySelector('button'),
        );
        expect(
            within(table)
                .getByRole('columnheader', { name: 'Author' })
                .querySelector('button'),
        ).toBeNull();
    });

    it('keeps plain headers without a sort', () => {
        render(
            <DataTable rows={posts} columns={sortable} rowKey={(p) => p.id} />,
        );

        expect(
            within(screen.getByRole('table')).queryByRole('button'),
        ).not.toBeInTheDocument();
    });

    it('asks for the column order when a header is pressed', async () => {
        const onSort = vi.fn();

        render(
            <DataTable
                rows={posts}
                columns={sortable}
                rowKey={(p) => p.id}
                sort={{ column: 'words', direction: 'descending', onSort }}
            />,
        );

        await userEvent.click(screen.getByRole('button', { name: 'Title' }));

        expect(onSort).toHaveBeenCalledWith('title');
    });

    it('maps server sort keys to columns', () => {
        const onChange = vi.fn();
        const sort = sortByKey<'recent' | 'hits' | 'path'>(
            {
                recent: { column: 'created', direction: 'descending' },
                hits: { column: 'hits', direction: 'descending' },
                path: { column: 'from', direction: 'ascending' },
            },
            'hits',
            onChange,
        );

        expect(sort.column).toBe('hits');
        expect(sort.direction).toBe('descending');

        sort.onSort('hits');
        expect(onChange).not.toHaveBeenCalled();

        sort.onSort('from');
        expect(onChange).toHaveBeenCalledWith('path');
    });
});
