import { fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { EDITOR_SIDEBAR_KEY } from '@/hooks/use-editor-sidebar';
import { LEAVE_MESSAGE } from '@/hooks/use-unsaved-changes';
import EntryEdit from '@/pages/admin/content/entries/edit';
import {
    fireRouterEvent,
    resetPageProps,
    setPageProps,
} from '@/tests/mocks/inertia';
import type { Entry } from '@/types';

// What the editor sent through useForm: the transform callback, and put/post calls.
const sent = vi.hoisted(() => ({
    transform: null as null | ((values: Record<string, unknown>) => unknown),
    put: vi.fn(),
    post: vi.fn(),
}));

vi.mock('@inertiajs/react', async () => {
    const { inertiaMock } = await import('@/tests/mocks/inertia');

    // A small useForm stand-in that keeps its first values, like Inertia.
    function useForm<T extends Record<string, unknown>>(initial: T) {
        const [data, setValues] = useState(initial);

        return {
            data,
            setData: (key: keyof T, value: unknown) =>
                setValues((current) => ({ ...current, [key]: value })),
            errors: {},
            processing: false,
            transform: (
                callback: (values: Record<string, unknown>) => unknown,
            ) => {
                sent.transform = callback;
            },
            put: (...args: unknown[]) => sent.put(data, ...args),
            post: (...args: unknown[]) => sent.post(data, ...args),
        };
    }

    return { ...inertiaMock, useForm };
});

// Makes matchMedia answer true for the given queries, like a real screen of that size.
function screenMatches(...queries: string[]) {
    vi.stubGlobal('matchMedia', (query: string) => ({
        matches: queries.includes(query),
        media: query,
        onchange: null,
        addListener: () => {},
        removeListener: () => {},
        addEventListener: () => {},
        removeEventListener: () => {},
        dispatchEvent: () => false,
    }));
}

const WIDE = '(min-width: 1024px)';
const PHONE = '(max-width: 639.98px)';
const EDITOR_ID = 7;

beforeEach(() => {
    window.localStorage.clear();
    sent.put.mockClear();
    sent.post.mockClear();
    sent.transform = null;
    setPageProps({ auth: { user: { id: EDITOR_ID, name: 'Inês Moreira' } } });
});

afterEach(() => {
    resetPageProps();
    vi.restoreAllMocks();
});

const contentType = {
    name: 'post',
    label: 'Post',
    pluralLabel: 'Posts',
    hierarchical: false,
    hasArchive: true,
    taxonomies: [],
    icon: 'entries',
};

const savedPost: Entry = {
    id: 42,
    type: 'post',
    title: 'Market Day in Braga',
    slug: 'market-day-in-braga',
    status: 'published',
    blocks: [],
    excerpt: 'Notes and photos from the Saturday market.',
    author: null,
    parent_id: null,
    menu_order: 0,
    published_at: '2026-11-17T08:24:00Z',
    meta: {},
    url: 'https://harborlane.pt/blog/market-day-in-braga',
    created_at: '2026-11-17T08:24:00Z',
    updated_at: '2026-11-17T08:24:00Z',
    deleted_at: null,
};

function props(entry: Entry | null) {
    return {
        contentType,
        entry,
        taxonomies: [],
        parents: [],
        statuses: [
            { value: 'draft' as const, label: 'Draft' },
            { value: 'published' as const, label: 'Published' },
        ],
        revisions: [],
        commentsOpen: true,
        pluginBlocks: [],
        can: { publish: true, delete: true },
    };
}

describe('EntryEdit after create redirects to edit', () => {
    it('shows the saved entry instead of the empty create form', () => {
        screenMatches(WIDE);
        const { rerender } = render(<EntryEdit {...props(null)} />);

        expect(screen.getByLabelText('Title')).toHaveValue('');
        expect(
            screen.getByRole('combobox', { name: 'Publish date' }),
        ).toHaveTextContent('Not set');

        rerender(<EntryEdit {...props(savedPost)} />);

        expect(screen.getByLabelText('Title')).toHaveValue(
            'Market Day in Braga',
        );
        expect(screen.getByLabelText('Excerpt')).toHaveValue(
            'Notes and photos from the Saturday market.',
        );
        expect(
            screen.getByRole('combobox', { name: 'Publish date' }),
        ).not.toHaveTextContent('Not set');
    });
});

describe('EntryEdit side panel', () => {
    it('renders taxonomies and the page template select together', () => {
        screenMatches(WIDE);
        render(
            <EntryEdit
                {...props(savedPost)}
                taxonomies={[
                    {
                        name: 'category',
                        label: 'Category',
                        pluralLabel: 'Categories',
                        hierarchical: true,
                        icon: 'terms',
                        terms: [
                            { id: 3, name: 'Travel', parent_id: null },
                            { id: 4, name: 'Food', parent_id: null },
                        ],
                    },
                ]}
                pageTemplates={[
                    {
                        value: 'full-width',
                        label: 'Full width',
                        description: 'No sidebar, wide content.',
                    },
                ]}
            />,
        );

        expect(screen.getByText('Categories')).toBeInTheDocument();
        expect(screen.getByText('Travel')).toBeInTheDocument();
        expect(screen.getByLabelText('Page template')).toBeInTheDocument();
    });
});

describe('EntryEdit header and footer styles', () => {
    const partChoices = [
        {
            name: 'header',
            label: 'Header',
            default: 'classic',
            options: [
                { value: 'classic', label: 'Classic', description: '' },
                {
                    value: 'minimal',
                    label: 'Minimal',
                    description: 'Only the logo, no menu.',
                },
            ],
        },
    ];

    it('shows a style select and sends the saved style back', async () => {
        screenMatches(WIDE);
        const user = userEvent.setup();
        render(
            <EntryEdit
                {...props({
                    ...savedPost,
                    meta: { parts: { header: 'minimal' } },
                })}
                partChoices={partChoices}
            />,
        );

        expect(screen.getByLabelText('Header style')).toHaveTextContent(
            'Minimal',
        );

        fireEvent.keyDown(window, { key: 's', metaKey: true });
        const values = sent.put.mock.calls[0][0] as Record<string, unknown>;
        const payload = sent.transform?.(values) as {
            meta: Record<string, unknown>;
        };
        expect(payload.meta.parts).toEqual({ header: 'minimal' });
        await user.keyboard('{Escape}');
    });

    it('sends no styles when the entry keeps the template or site style', () => {
        screenMatches(WIDE);
        render(
            <EntryEdit {...props(savedPost)} partChoices={partChoices} />,
        );

        expect(screen.getByLabelText('Header style')).toHaveTextContent(
            'Same as the template or the site',
        );

        fireEvent.keyDown(window, { key: 's', metaKey: true });
        const values = sent.put.mock.calls[0][0] as Record<string, unknown>;
        const payload = sent.transform?.(values) as {
            meta: Record<string, unknown>;
        };
        expect(payload.meta.parts).toBeUndefined();
    });
});

describe('EntryEdit full screen layout', () => {
    it('renders its own frame without the admin shell', () => {
        const { container } = render(<EntryEdit {...props(savedPost)} />);

        expect(
            (EntryEdit as unknown as { layout?: unknown }).layout,
        ).toBeUndefined();
        expect(
            container.querySelector('[data-test="editor-layout"]'),
        ).toBeInTheDocument();
        expect(screen.queryByText('Skip to content')).not.toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Back to posts' }),
        ).toHaveAttribute('href', '/admin/entries/post');
        expect(screen.getByLabelText('Title')).toHaveAttribute(
            'placeholder',
            'Add title',
        );
        expect(screen.getByRole('status')).toHaveTextContent('Saved');
    });

    it('shows Publish and Save draft for a new entry and Update for a published one', () => {
        const { unmount } = render(<EntryEdit {...props(null)} />);

        expect(
            screen.getByRole('button', { name: 'Publish' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Save draft' }),
        ).toBeInTheDocument();
        unmount();

        render(<EntryEdit {...props(savedPost)} />);

        expect(
            screen.getByRole('button', { name: 'Update' }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Save draft' }),
        ).not.toBeInTheDocument();
    });
});

describe('EntryEdit settings sidebar', () => {
    it('hides and shows the sidebar and remembers the choice per user', async () => {
        screenMatches(WIDE);
        const user = userEvent.setup();
        const { unmount } = render(<EntryEdit {...props(savedPost)} />);
        const toggle = screen.getByRole('button', { name: 'Settings' });

        expect(toggle).toHaveAttribute('aria-expanded', 'true');
        expect(screen.getByRole('tab', { name: 'Post' })).toBeVisible();

        await user.click(toggle);

        expect(toggle).toHaveAttribute('aria-expanded', 'false');
        expect(
            window.localStorage.getItem(`${EDITOR_SIDEBAR_KEY}:${EDITOR_ID}`),
        ).toBe('closed');
        unmount();

        render(<EntryEdit {...props(savedPost)} />);

        expect(
            screen.getByRole('button', { name: 'Settings' }),
        ).toHaveAttribute('aria-expanded', 'false');
    });

    it('toggles with Cmd+Shift+comma', () => {
        screenMatches(WIDE);
        render(<EntryEdit {...props(savedPost)} />);
        const toggle = screen.getByRole('button', { name: 'Settings' });

        fireEvent.keyDown(window, {
            key: '<',
            code: 'Comma',
            metaKey: true,
            shiftKey: true,
        });

        expect(toggle).toHaveAttribute('aria-expanded', 'false');
    });

    it('opens as a sheet on smaller screens', async () => {
        const user = userEvent.setup();
        render(<EntryEdit {...props(savedPost)} />);

        expect(screen.queryByRole('tab', { name: 'Post' })).toBeNull();

        await user.click(screen.getByRole('button', { name: 'Settings' }));

        expect(
            await screen.findByRole('dialog', { name: 'Settings' }),
        ).toBeInTheDocument();
        expect(screen.getByRole('tab', { name: 'Post' })).toHaveAttribute(
            'aria-selected',
            'true',
        );
    });
});

describe('EntryEdit saving', () => {
    it('saves with Cmd+S', () => {
        render(<EntryEdit {...props(savedPost)} />);

        fireEvent.keyDown(window, { key: 's', metaKey: true });

        expect(sent.put).toHaveBeenCalledTimes(1);
        expect(sent.put.mock.calls[0][1]).toBe('/admin/entries/post/42');
    });

    it('sends the published status when Publish is pressed', async () => {
        const user = userEvent.setup();
        render(<EntryEdit {...props(null)} />);

        await user.type(screen.getByLabelText('Title'), 'Spring at the Bakery');
        await user.click(screen.getByRole('button', { name: 'Publish' }));

        expect(sent.post).toHaveBeenCalledTimes(1);
        const values = sent.post.mock.calls[0][0] as Record<string, unknown>;
        const payload = sent.transform?.(values) as Record<string, unknown>;
        expect(payload.status).toBe('published');
        expect(payload.title).toBe('Spring at the Bakery');
    });
});

describe('EntryEdit unsaved changes', () => {
    it('asks before leaving only when something changed', async () => {
        const user = userEvent.setup();
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        render(<EntryEdit {...props(savedPost)} />);
        const visit = { visit: { prefetch: false, only: [] } };

        expect(fireRouterEvent('before', visit)).toBe(true);
        expect(confirm).not.toHaveBeenCalled();

        await user.type(screen.getByLabelText('Title'), ' at Dawn');

        expect(screen.getByRole('status')).toHaveTextContent('Unsaved changes');
        expect(fireRouterEvent('before', visit)).toBe(false);
        expect(confirm).toHaveBeenCalledWith(LEAVE_MESSAGE);

        const unload = new Event('beforeunload', { cancelable: true });
        window.dispatchEvent(unload);
        expect(unload.defaultPrevented).toBe(true);
    });
});

describe('EntryEdit on phones', () => {
    it('moves Preview and Save draft into the more actions menu', async () => {
        screenMatches(PHONE);
        const user = userEvent.setup();
        render(<EntryEdit {...props({ ...savedPost, status: 'draft' })} />);

        await user.click(screen.getByRole('button', { name: 'More actions' }));

        const names = (await screen.findAllByRole('menuitem')).map(
            (item) => item.textContent,
        );
        expect(names).toEqual([
            'Preview',
            'Save draft',
            'Copy link',
            'View revisions',
            'Keyboard shortcuts',
            'Move to trash',
        ]);
        expect(
            screen.getByRole('button', { name: 'Publish' }),
        ).toBeInTheDocument();
    });
});
