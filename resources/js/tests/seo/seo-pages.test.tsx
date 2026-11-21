import {
    act,
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/react';
import { useState } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { NotFoundRow, SeoCheckRow } from '@/components/verso/seo/types';
import AltTextPage, { fetchSuggestion } from '@/pages/admin/seo/alt-text';
import SeoIssue from '@/pages/admin/seo/issue';
import NotFoundMonitor from '@/pages/admin/seo/not-found';
import SeoOverview from '@/pages/admin/seo/overview';
import { router } from '@/tests/mocks/inertia';
import type { MediaLibraryItem, Paginated } from '@/types';

const formPost = vi.fn();

// A small useForm stand-in that keeps data in React state.
function useForm<T extends Record<string, unknown>>(initial: T) {
    const [data, setDataState] = useState(initial);
    let transform = (value: T): unknown => value;

    return {
        data,
        errors: {} as Record<string, string>,
        processing: false,
        setData: (key: keyof T, value: unknown) =>
            setDataState((current) => ({ ...current, [key]: value })),
        transform: (callback: (value: T) => unknown) => {
            transform = callback;
        },
        post: (url: string) => formPost(url, transform(data)),
    };
}

vi.mock('@inertiajs/react', async () => ({
    ...(await import('@/tests/mocks/inertia')).inertiaMock,
    useForm,
}));

function page<T>(data: T[]): Paginated<T> {
    return {
        data,
        current_page: 1,
        last_page: 1,
        per_page: 25,
        total: data.length,
        from: data.length ? 1 : null,
        to: data.length || null,
        links: [],
    };
}

function check(overrides: Partial<SeoCheckRow>): SeoCheckRow {
    return {
        key: 'missing-description',
        label: 'Pages without a meta description',
        description: 'The description is the short text under your title.',
        severity: 'warning',
        severityLabel: 'Should fix',
        weight: 3,
        affected: 4,
        total: 20,
        pending: false,
        summary: '',
        hasItems: true,
        fix: null,
        href: '/admin/seo/issues/missing-description',
        fixUrl: null,
        ...overrides,
    };
}

const linkCheck = {
    status: 'idle' as const,
    started_at: null,
    finished_at: null,
    updated_at: null,
    total: 0,
    checked: 0,
    running: false,
};

beforeEach(() => {
    vi.clearAllMocks();
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('SEO overview', () => {
    const props = {
        score: 64,
        thresholds: { good: 70, ok: 40 },
        checkedAt: '2026-11-18T09:30:00+00:00',
        checks: [
            check({}),
            check({
                key: 'search-discouraged',
                label: 'Search engines are asked to stay away',
                severity: 'critical',
                severityLabel: 'Important',
                affected: 1,
                total: 1,
                summary: 'Every page tells search engines not to show it.',
                hasItems: false,
                fix: {
                    label: 'Let search engines in',
                    kind: 'action',
                    url: null,
                },
                fixUrl: '/admin/seo/issues/search-discouraged/fix',
            }),
            check({
                key: 'duplicate-titles',
                label: 'Pages with the same title',
                affected: 0,
            }),
        ],
        ratings: { good: 6, ok: 3, poor: 1, none: 10 },
        linkCheck,
        notFound: {
            total: 2,
            hits: 19,
            top: [{ id: 1, path: '/blog/riding-tram28', hits: 14 }],
            enabled: true,
        },
        can: { manage: true },
    };

    it('shows the score, the counts and the checks to fix', () => {
        render(<SeoOverview {...props} />);

        expect(
            screen.getByRole('meter', { name: 'SEO score of the site' }),
        ).toHaveAttribute('value', '64');
        expect(screen.getByText('A few things to improve')).toBeInTheDocument();
        expect(
            document.querySelector('[data-check="search-discouraged"]'),
        ).toBeInTheDocument();
        expect(
            document.querySelector('[data-check="duplicate-titles"]'),
        ).toBeNull();
        expect(screen.getByText('4 of 20')).toBeInTheDocument();
        expect(screen.getByText('/blog/riding-tram28')).toBeInTheDocument();
    });

    it('filters the checks and runs a one click fix', () => {
        render(<SeoOverview {...props} />);

        fireEvent.click(screen.getByRole('button', { name: /Passed/ }));
        expect(
            document.querySelector('[data-check="duplicate-titles"]'),
        ).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: /^Important/ }));
        fireEvent.click(
            screen.getByRole('button', { name: 'Let search engines in' }),
        );

        expect(router.post).toHaveBeenCalledWith(
            '/admin/seo/issues/search-discouraged/fix',
            {},
            { preserveScroll: true },
        );
    });

    it('hides actions from people who may only look', () => {
        render(<SeoOverview {...props} can={{ manage: false }} />);

        expect(
            screen.queryByRole('button', { name: 'Count again' }),
        ).toBeNull();
        expect(
            screen.queryByRole('button', { name: 'Check links now' }),
        ).toBeNull();
    });

    it('reloads while the link check runs', () => {
        vi.useFakeTimers();
        render(
            <SeoOverview
                {...props}
                linkCheck={{
                    ...linkCheck,
                    status: 'running',
                    running: true,
                    total: 40,
                    checked: 10,
                }}
            />,
        );

        expect(screen.getByText('Checking 10 of 40 pages')).toBeInTheDocument();
        act(() => {
            vi.advanceTimersByTime(5000);
        });
        expect(router.reload).toHaveBeenCalled();
        vi.useRealTimers();
    });
});

describe('SEO issue list', () => {
    it('lists the items with their fix', () => {
        render(
            <SeoIssue
                check={check({})}
                items={page([
                    {
                        key: 'entry-4',
                        title: 'Opening hours for the summer',
                        detail: 'No meta description and no excerpt.',
                        editUrl: '/admin/entries/page/4/edit',
                        viewUrl: null,
                        type: 'page',
                        typeLabel: 'Page',
                    },
                ])}
                filters={{ type: '', search: '' }}
                types={[{ value: 'page', label: 'Pages' }]}
                shown={4}
                linkCheck={linkCheck}
                can={{ manage: true }}
            />,
        );

        expect(
            screen.getAllByText('Opening hours for the summer')[0].closest('a'),
        ).toHaveAttribute('href', '/admin/entries/page/4/edit');
        expect(
            screen.getAllByText('No meta description and no excerpt.').length,
        ).toBeGreaterThan(0);
    });
});

describe('404 monitor', () => {
    const row: NotFoundRow = {
        id: 7,
        path: '/blog/riding-tram28',
        hits: 14,
        referrer_host: 'www.google.com',
        first_seen_at: '2026-11-10T08:00:00+00:00',
        last_seen_at: '2026-11-18T08:00:00+00:00',
        redirect: null,
        suggestions: [
            {
                path: '/blog/riding-tram-28',
                title: 'Riding Tram 28 in Lisbon',
                similarity: 95,
            },
        ],
    };
    const props = {
        rows: page([row]),
        filters: { search: '', sort: 'hits' as const },
        totals: { paths: 1, hits: 14 },
        settings: { enabled: true, retentionDays: 30, maxRows: 5000 },
        statusCodes: [301, 302],
        defaultStatus: 301,
        can: { manage: true },
    };

    it('shows the rows with the closest page', () => {
        render(<NotFoundMonitor {...props} />);

        expect(
            screen.getAllByText('/blog/riding-tram28').length,
        ).toBeGreaterThan(0);
        expect(
            screen.getAllByText('Riding Tram 28 in Lisbon').length,
        ).toBeGreaterThan(0);
    });

    it('creates a redirect to the suggested page', async () => {
        render(<NotFoundMonitor {...props} />);

        fireEvent.pointerDown(
            screen.getAllByRole('button', {
                name: /Actions|More|Open menu/i,
            })[0],
            { button: 0, pointerType: 'mouse' },
        );
        fireEvent.click(
            await screen.findByRole('menuitem', { name: 'Create redirect' }),
        );

        expect(screen.getByLabelText('Send visitors to')).toHaveValue(
            '/blog/riding-tram-28',
        );
        fireEvent.click(
            screen.getByRole('button', { name: 'Create redirect' }),
        );

        expect(formPost).toHaveBeenCalledWith(
            '/admin/seo/not-found/7/redirect',
            { to_path: '/blog/riding-tram-28', status_code: 301 },
        );
    });

    it('warns when the monitor is off', () => {
        render(
            <NotFoundMonitor
                {...props}
                settings={{ ...props.settings, enabled: false }}
            />,
        );

        expect(screen.getByText('The 404 monitor is off')).toBeInTheDocument();
    });
});

describe('Add alt text', () => {
    const image = {
        id: 12,
        url: '/storage/2026/11/bakery-window.jpg',
        filename: 'bakery-window.jpg',
        mime_type: 'image/jpeg',
        size: 120000,
        width: 1600,
        height: 1067,
        alt: null,
        caption: null,
        path: '2026/11/bakery-window.jpg',
        type: 'image',
        thumbnail_url: '/img/2026/11/bakery-window.jpg?w=320',
        uploaded_by: 'Marta Ilves',
        created_at: null,
        can: { update: true, delete: true },
    } satisfies MediaLibraryItem;
    const props = {
        images: page([image]),
        filters: { show: 'missing' as const, search: '' },
        missing: 1,
        maxLength: 250,
        suggester: {
            label: 'AI Assistant',
            endpoint: '/admin/ai-assistant/actions/alt-text',
            ready: true,
            settingsUrl: null,
        },
    };

    it('saves only the changed images', () => {
        render(<AltTextPage {...props} />);

        fireEvent.change(
            screen.getByLabelText('Alt text for bakery-window.jpg'),
            {
                target: { value: 'Morning light on the bakery window' },
            },
        );
        fireEvent.click(screen.getByRole('button', { name: 'Save alt text' }));

        expect(router.put).toHaveBeenCalledWith(
            '/admin/seo/alt-text',
            { items: [{ id: 12, alt: 'Morning light on the bakery window' }] },
            expect.anything(),
        );
    });

    it('shows a suggestion as a preview until it is accepted', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue(
                new Response(
                    JSON.stringify({
                        result: {
                            alt: 'A bakery window with bread on shelves',
                        },
                    }),
                    { status: 200 },
                ),
            ),
        );
        render(<AltTextPage {...props} />);

        fireEvent.click(
            screen.getByRole('button', { name: 'Suggest with AI Assistant' }),
        );
        await screen.findByText('A bakery window with bread on shelves');

        expect(
            screen.getByLabelText('Alt text for bakery-window.jpg'),
        ).toHaveValue('');
        fireEvent.click(screen.getByRole('button', { name: 'Use it' }));
        expect(
            screen.getByLabelText('Alt text for bakery-window.jpg'),
        ).toHaveValue('A bakery window with bread on shelves');
    });

    it('shows the error of a failed suggestion', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockImplementation(
                async () =>
                    new Response(
                        JSON.stringify({
                            message:
                                'Too many requests. Try again in a minute.',
                        }),
                        { status: 429 },
                    ),
            ),
        );

        await expect(
            fetchSuggestion('/admin/ai-assistant/actions/alt-text', 12),
        ).rejects.toThrow('Too many requests. Try again in a minute.');

        render(<AltTextPage {...props} />);
        fireEvent.click(
            screen.getByRole('button', { name: 'Suggest with AI Assistant' }),
        );
        await waitFor(() =>
            expect(screen.getByRole('alert')).toHaveTextContent(
                'Too many requests',
            ),
        );
    });

    it('asks for a key when the suggester is not ready', () => {
        render(
            <AltTextPage
                {...props}
                suggester={{ ...props.suggester, ready: false }}
            />,
        );

        expect(
            screen.getByText('AI Assistant can suggest alt text'),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: /Suggest with/ }),
        ).toBeNull();
    });
});
