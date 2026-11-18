import { fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { fetchLibrary } from '@/components/verso/media/media-api';
import { SeoPanel, seoFromMeta } from '@/components/verso/site/seo-panel';
import type { EntrySeoFields } from '@/types';
import { libraryPage, mediaItem } from '../media/fixtures';

vi.mock('@/components/verso/media/media-api', () => ({
    fetchLibrary: vi.fn(),
    uploadMedia: vi.fn(),
    acceptAttribute: (accept: string) =>
        accept === 'any' ? undefined : `${accept}/*`,
}));

const empty: EntrySeoFields = {
    title: null,
    description: null,
    canonical: null,
    noindex: false,
    og_image: null,
    sitemap_exclude: false,
    focus_keyphrase: null,
    nofollow: false,
    og_title: null,
    og_description: null,
    score: null,
};

// Holds the SEO fields in state like the entry form does.
function Panel({
    initial = empty,
    onChange = () => {},
    url = 'https://harborlane.example/blog/winter-menu',
}: {
    initial?: EntrySeoFields;
    onChange?: (value: EntrySeoFields) => void;
    url?: string | null;
}) {
    const [value, setValue] = useState(initial);

    return (
        <SeoPanel
            value={value}
            onChange={(next) => {
                setValue(next);
                onChange(next);
            }}
            fallbackTitle="Our winter menu"
            fallbackDescription="Soups, stews and fresh bread for cold days."
            url={url}
        />
    );
}

const titleInput = () => screen.getByLabelText('SEO title');
const descriptionInput = () => screen.getByLabelText('Meta description');
const preview = () => screen.getByLabelText('Search result preview');

beforeEach(() => {
    vi.mocked(fetchLibrary).mockResolvedValue(libraryPage([]));
});

describe('seoFromMeta', () => {
    it('returns empty fields when meta has no seo', () => {
        expect(seoFromMeta(undefined)).toEqual(empty);
        expect(seoFromMeta({ featured_image: { mediaId: 4 } })).toEqual(empty);
    });

    it('reads the stored fields', () => {
        expect(
            seoFromMeta({
                seo: {
                    title: 'Winter menu 2026',
                    description: 'Warm food in Porto.',
                    canonical: 'https://harborlane.example/menu',
                    noindex: true,
                    og_image: '/img/menu.jpg',
                },
            }),
        ).toEqual({
            title: 'Winter menu 2026',
            description: 'Warm food in Porto.',
            canonical: 'https://harborlane.example/menu',
            noindex: true,
            og_image: '/img/menu.jpg',
            sitemap_exclude: false,
            focus_keyphrase: null,
            nofollow: false,
            og_title: null,
            og_description: null,
            score: null,
        });
    });

    it('reads the sitemap switch', () => {
        expect(
            seoFromMeta({ seo: { sitemap_exclude: true } }).sitemap_exclude,
        ).toBe(true);
    });

    it('lets the editor leave an entry out of the sitemap', async () => {
        const onChange = vi.fn();
        render(<Panel onChange={onChange} />);

        await userEvent.click(
            screen.getByRole('checkbox', { name: /Leave out of the sitemap/ }),
        );

        expect(onChange).toHaveBeenLastCalledWith({
            ...empty,
            sitemap_exclude: true,
        });
    });

    it('ignores values of the wrong type', () => {
        expect(
            seoFromMeta({
                seo: {
                    title: 42,
                    description: ['Warm food'],
                    canonical: false,
                    noindex: 'yes',
                    og_image: { url: '/img/menu.jpg' },
                },
            }),
        ).toEqual(empty);
    });
});

describe('SeoPanel counters', () => {
    it('starts both counters at zero', () => {
        render(<Panel />);

        expect(screen.getByText('0 / 60')).toBeInTheDocument();
        expect(screen.getByText('0 / 160')).toBeInTheDocument();
    });

    it('counts the characters of the title', () => {
        render(<Panel />);

        fireEvent.change(titleInput(), {
            target: { value: 'Winter menu at Harbor Lane' },
        });

        expect(screen.getByText('26 / 60')).toBeInTheDocument();
    });

    it('counts the characters of the description', () => {
        render(<Panel />);

        fireEvent.change(descriptionInput(), {
            target: { value: 'Soups and stews.' },
        });

        expect(screen.getByText('16 / 160')).toBeInTheDocument();
    });

    it('stays calm at exactly the limit', () => {
        render(<Panel initial={{ ...empty, title: 'a'.repeat(60) }} />);

        expect(screen.getByText('60 / 60')).toHaveClass(
            'text-muted-foreground',
        );
    });

    it('turns amber when the title is too long', () => {
        render(<Panel initial={{ ...empty, title: 'b'.repeat(61) }} />);

        expect(screen.getByText('61 / 60')).toHaveClass('text-warning-text');
    });

    it('turns amber when the description is too long', () => {
        render(<Panel initial={{ ...empty, description: 'c'.repeat(161) }} />);

        expect(screen.getByText('161 / 160')).toHaveClass('text-warning-text');
    });

    it('counts letters with accents as one character each', () => {
        render(<Panel />);

        fireEvent.change(titleInput(), { target: { value: 'Pão de Ló' } });

        expect(screen.getByText('9 / 60')).toBeInTheDocument();
    });

    it('updates the counter while typing', async () => {
        render(<Panel />);

        await userEvent.type(titleInput(), 'Rye');

        expect(screen.getByText('3 / 60')).toBeInTheDocument();
    });
});

describe('SeoPanel fields', () => {
    it('saves null when a text field holds only spaces', () => {
        const onChange = vi.fn();
        render(<Panel onChange={onChange} />);

        fireEvent.change(titleInput(), { target: { value: '   ' } });

        expect(onChange).toHaveBeenLastCalledWith({ ...empty, title: null });
    });

    it('keeps the typed text as it is', () => {
        const onChange = vi.fn();
        render(<Panel onChange={onChange} />);

        fireEvent.change(descriptionInput(), {
            target: { value: ' Fresh bread daily ' },
        });

        expect(onChange).toHaveBeenLastCalledWith({
            ...empty,
            description: ' Fresh bread daily ',
        });
    });

    it('uses the fallbacks as placeholders', () => {
        render(<Panel />);

        expect(titleInput()).toHaveAttribute('placeholder', 'Our winter menu');
        expect(descriptionInput()).toHaveAttribute(
            'placeholder',
            'Soups, stews and fresh bread for cold days.',
        );
        expect(screen.getByLabelText('Canonical URL')).toHaveAttribute(
            'placeholder',
            'https://harborlane.example/blog/winter-menu',
        );
    });

    it('saves the canonical URL', () => {
        const onChange = vi.fn();
        render(<Panel onChange={onChange} />);

        fireEvent.change(screen.getByLabelText('Canonical URL'), {
            target: { value: 'https://harborlane.example/menu' },
        });

        expect(onChange).toHaveBeenLastCalledWith({
            ...empty,
            canonical: 'https://harborlane.example/menu',
        });
    });

    it('toggles noindex', async () => {
        const onChange = vi.fn();
        render(<Panel onChange={onChange} />);

        await userEvent.click(
            screen.getByRole('checkbox', { name: /Hide from search engines/ }),
        );

        expect(onChange).toHaveBeenLastCalledWith({ ...empty, noindex: true });
    });
});

describe('SeoPanel preview', () => {
    it('shows the fallbacks and the entry URL', () => {
        render(<Panel />);

        expect(preview()).toHaveTextContent(
            'harborlane.example › blog › winter-menu',
        );
        expect(preview()).toHaveTextContent('Our winter menu');
        expect(preview()).toHaveTextContent(
            'Soups, stews and fresh bread for cold days.',
        );
    });

    it('prefers the SEO values', () => {
        render(
            <Panel
                initial={{
                    ...empty,
                    title: 'Winter menu 2026',
                    description: 'Warm food by the harbor.',
                    canonical: 'https://harborlane.example/menu',
                }}
            />,
        );

        expect(preview()).toHaveTextContent('harborlane.example › menu');
        expect(preview()).toHaveTextContent('Winter menu 2026');
        expect(preview()).toHaveTextContent('Warm food by the harbor.');
    });

    it('shows placeholders when nothing is known', () => {
        render(
            <SeoPanel
                value={empty}
                onChange={vi.fn()}
                fallbackTitle=""
                url={null}
            />,
        );

        expect(preview()).toHaveTextContent('https://…');
        expect(preview()).toHaveTextContent('Untitled');
        expect(preview()).toHaveTextContent('No description yet.');
    });
});

describe('SeoPanel share image', () => {
    it('shows the image with a remove button', async () => {
        const onChange = vi.fn();
        render(
            <Panel
                onChange={onChange}
                initial={{ ...empty, og_image: '/img/winter-menu.jpg' }}
            />,
        );

        expect(document.querySelector('img')).toHaveAttribute(
            'src',
            '/img/winter-menu.jpg',
        );

        await userEvent.click(
            screen.getByRole('button', { name: 'Remove share image' }),
        );

        expect(onChange).toHaveBeenLastCalledWith({ ...empty, og_image: null });
        expect(document.querySelector('img')).toBeNull();
    });

    it('picks the image from the media library', async () => {
        vi.mocked(fetchLibrary).mockResolvedValue(
            libraryPage([
                mediaItem(7, {
                    filename: 'soup.jpg',
                    alt: 'Pumpkin soup',
                    url: '/storage/media/soup.jpg',
                }),
            ]),
        );
        const onChange = vi.fn();
        render(<Panel onChange={onChange} />);

        await userEvent.click(
            screen.getByRole('button', { name: 'Pick from the media library' }),
        );
        await userEvent.dblClick(
            await screen.findByRole('button', { name: 'Pumpkin soup' }),
        );

        expect(fetchLibrary).toHaveBeenCalledWith(
            expect.objectContaining({ accept: 'image' }),
        );
        expect(onChange).toHaveBeenLastCalledWith({
            ...empty,
            og_image: '/storage/media/soup.jpg',
        });
        expect(screen.getByLabelText('Share image')).toHaveValue(
            '/storage/media/soup.jpg',
        );
    });
});
