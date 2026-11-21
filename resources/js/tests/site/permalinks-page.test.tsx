import { fireEvent, render, screen } from '@testing-library/react';
import { useState } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    newIndexNowKey,
    previewTitle,
} from '@/components/verso/site/permalinks/search-engines-form';
import PermalinksPage from '@/pages/admin/site/permalinks';

const formPut = vi.fn();
let currentUrl = '/admin/site/permalinks';

// A small useForm stand-in that keeps data in React state.
function useForm<T extends Record<string, unknown>>(initial: T) {
    const [data, setDataState] = useState(initial);
    let transform = (value: T): unknown => value;

    return {
        data,
        errors: {} as Record<string, string>,
        processing: false,
        isDirty: JSON.stringify(data) !== JSON.stringify(initial),
        setData: (key: keyof T | ((current: T) => T), value?: unknown) =>
            setDataState((current) =>
                typeof key === 'function'
                    ? key(current)
                    : { ...current, [key]: value },
            ),
        transform: (callback: (value: T) => unknown) => {
            transform = callback;
        },
        put: (url: string) => formPut(url, transform(data)),
    };
}

vi.mock('@inertiajs/react', async () => ({
    ...(await import('@/tests/mocks/inertia')).inertiaMock,
    useForm,
    usePage: () => ({ props: {}, url: currentUrl }),
}));

vi.mock('@/components/verso/media/media-api', () => ({
    fetchLibrary: vi.fn(),
    uploadMedia: vi.fn(),
    acceptAttribute: () => 'image/*',
}));

type Props = Parameters<typeof PermalinksPage>[0];

function pageProps(): Props {
    return {
        permalinks: {
            structures: { post: '/blog/{slug}', page: '/{slug}' },
            archive_bases: { post: 'blog' },
            taxonomy_bases: { category: 'category', tag: 'tag' },
            trailing_slash: false,
            lowercase: false,
        },
        types: [
            {
                name: 'post',
                label: 'Post',
                pluralLabel: 'Posts',
                inlineLabel: 'post',
                inlinePluralLabel: 'posts',
                hierarchical: false,
                hasArchive: true,
                defaultStructure: '/blog/{slug}',
                defaultArchiveBase: 'blog',
                previous: ['/{year}/{month}/{slug}'],
                sample: {
                    slug: 'riding-tram-28',
                    id: '418',
                    year: '2026',
                    month: '03',
                    day: '09',
                    category: 'travel',
                },
            },
            {
                name: 'page',
                label: 'Page',
                pluralLabel: 'Pages',
                inlineLabel: 'page',
                inlinePluralLabel: 'pages',
                hierarchical: true,
                hasArchive: false,
                defaultStructure: '/{slug}',
                defaultArchiveBase: '',
                previous: [],
                sample: { slug: 'about/team', id: '7' },
            },
        ],
        taxonomies: [
            {
                name: 'category',
                label: 'Category',
                defaultBase: 'category',
                sample: 'travel',
            },
            { name: 'tag', label: 'Tag', defaultBase: 'tag', sample: 'travel' },
        ],
        presets: {
            plain: '/{slug}',
            blog: '/blog/{slug}',
            month: '/{year}/{month}/{slug}',
        },
        tokens: ['slug', 'id', 'year', 'month', 'day', 'category'],
        trailingSlashNotice: true,
        sitemap: {
            enabled: true,
            types: ['post', 'page'],
            taxonomies: ['category'],
            excluded_entries: [12],
            include_images: false,
            max_urls: 5000,
            last_modified: 'updated',
        },
        sitemapStatus: { post: 14, page: 5, 'taxonomy-category': 3 },
        entryOptions: [
            { value: '12', label: 'Staff Rota', group: 'Pages' },
            { value: '15', label: 'Riding Tram 28', group: 'Posts' },
        ],
        search: {
            discourage: false,
            robots_txt: 'User-agent: *\nDisallow: /admin\n',
            title_format: '{title}{sep}{site}',
            title_separator: '|',
            default_description: '',
            default_image: null,
            schema_type: 'organization',
            schema_name: '',
            schema_logo: null,
            schema_same_as: ['https://www.instagram.com/harborlane'],
            verification_google: '',
            verification_bing: '',
            indexnow_enabled: false,
            indexnow_key: '',
        },
        links: {
            sitemap: 'https://harborlane.example/sitemap.xml',
            robots: 'https://harborlane.example/robots.txt',
            site: 'https://harborlane.example',
        },
        robotsDefault: 'User-agent: *\nDisallow: /admin\n',
        site: { name: 'Harbor Lane', tagline: 'Bread from the old port' },
    };
}

function openTab(tab: string) {
    currentUrl = `/admin/site/permalinks?tab=${tab}`;
    render(<PermalinksPage {...pageProps()} />);
}

beforeEach(() => {
    formPut.mockReset();
});

describe('URL structure tab', () => {
    it('shows a live example for each type', () => {
        openTab('permalinks');

        expect(
            screen.getByText('https://harborlane.example/blog/riding-tram-28'),
        ).toBeInTheDocument();
        expect(
            screen.getByText('https://harborlane.example/about/team'),
        ).toBeInTheDocument();
        expect(
            screen.getByText(/redirect from: \/\{year\}/),
        ).toBeInTheDocument();
    });

    it('updates the example while the pattern is typed', () => {
        openTab('permalinks');
        const input = screen.getAllByLabelText('Pattern')[0];

        fireEvent.change(input, {
            target: { value: '/{year}/{month}/{slug}' },
        });

        expect(
            screen.getByText(
                'https://harborlane.example/2026/03/riding-tram-28',
            ),
        ).toBeInTheDocument();
    });

    it('explains a broken pattern before saving', () => {
        openTab('permalinks');

        fireEvent.change(screen.getAllByLabelText('Pattern')[0], {
            target: { value: '/{year}/{month}' },
        });

        expect(
            screen.getByText('The structure needs {slug} or {id}.'),
        ).toBeInTheDocument();
    });

    it('adds a token with the token buttons', () => {
        openTab('permalinks');

        fireEvent.click(screen.getAllByRole('button', { name: '{id}' })[0]);

        expect(screen.getAllByLabelText('Pattern')[0]).toHaveValue(
            '/blog/{slug}/{id}',
        );
    });

    it('warns about the trailing slash rule and adds the slash to examples', () => {
        openTab('permalinks');

        fireEvent.click(
            screen.getByRole('checkbox', {
                name: /End addresses with a slash/,
            }),
        );

        expect(
            screen.getByText('Your web server removes trailing slashes'),
        ).toBeInTheDocument();
        expect(
            screen.getByText('https://harborlane.example/blog/riding-tram-28/'),
        ).toBeInTheDocument();
    });

    it('saves the structures', () => {
        openTab('permalinks');

        fireEvent.change(screen.getByLabelText('Archive base'), {
            target: { value: 'journal' },
        });
        fireEvent.submit(
            screen.getByRole('button', { name: 'Save URL structure' }),
        );

        expect(formPut).toHaveBeenCalledWith(
            '/admin/site/permalinks',
            expect.objectContaining({ archive_bases: { post: 'journal' } }),
        );
    });
});

describe('Sitemap tab', () => {
    it('shows the status and the entries left out', () => {
        openTab('sitemap');

        expect(
            screen.getByText('22 addresses in 3 sitemaps'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'View sitemap' }),
        ).toHaveAttribute('href', 'https://harborlane.example/sitemap.xml');
        expect(
            screen.getByRole('list', { name: 'Entries left out' }),
        ).toHaveTextContent('Staff Rota');
    });

    it('includes an entry again and saves', () => {
        openTab('sitemap');

        fireEvent.click(
            screen.getByRole('button', { name: 'Include Staff Rota again' }),
        );
        fireEvent.click(screen.getByRole('checkbox', { name: /Pages/ }));
        fireEvent.submit(
            screen.getByRole('button', { name: 'Save sitemap settings' }),
        );

        expect(formPut).toHaveBeenCalledWith(
            '/admin/site/permalinks/sitemap',
            expect.objectContaining({ excluded_entries: [], types: ['post'] }),
        );
    });
});

describe('Search engines tab', () => {
    it('previews the title format', () => {
        openTab('search');

        expect(
            screen.getByText('Winter Menu | Harbor Lane'),
        ).toBeInTheDocument();

        fireEvent.change(screen.getByLabelText('Title format'), {
            target: { value: '{site}{sep}{title}' },
        });

        expect(
            screen.getByText('Harbor Lane | Winter Menu'),
        ).toBeInTheDocument();
    });

    it('restores the safe robots.txt default', () => {
        openTab('search');
        const robots = screen.getByLabelText('robots.txt');

        fireEvent.change(robots, { target: { value: 'User-agent: *' } });
        fireEvent.click(
            screen.getByRole('button', { name: 'Use safe default' }),
        );

        expect(robots).toHaveValue('User-agent: *\nDisallow: /admin\n');
    });

    it('creates an IndexNow key when it is turned on and sends lists as arrays', () => {
        openTab('search');

        fireEvent.click(
            screen.getByRole('checkbox', { name: /Ping IndexNow/ }),
        );
        expect(
            (screen.getByLabelText('Key') as HTMLInputElement).value,
        ).toMatch(/^[0-9a-f]{32}$/);

        fireEvent.change(screen.getByLabelText('Profiles'), {
            target: {
                value: 'https://www.instagram.com/harborlane\n\nhttps://www.linkedin.com/company/harborlane ',
            },
        });
        fireEvent.submit(
            screen.getByRole('button', { name: 'Save search engine settings' }),
        );

        expect(formPut).toHaveBeenCalledWith(
            '/admin/site/permalinks/search',
            expect.objectContaining({
                indexnow_enabled: true,
                default_image: null,
                schema_same_as: [
                    'https://www.instagram.com/harborlane',
                    'https://www.linkedin.com/company/harborlane',
                ],
            }),
        );
    });
});

describe('previewTitle', () => {
    it('drops a separator left alone by an empty part', () => {
        expect(
            previewTitle('{title}{sep}{tagline}', '|', {
                name: 'Harbor Lane',
                tagline: '',
            }),
        ).toBe('Winter Menu');
    });

    it('makes keys of letters and digits only', () => {
        expect(newIndexNowKey()).toMatch(/^[0-9a-f]{32}$/);
    });
});
