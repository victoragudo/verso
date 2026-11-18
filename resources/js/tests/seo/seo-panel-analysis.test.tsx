import { act, fireEvent, render, screen } from '@testing-library/react';
import { useState } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { fetchLibrary } from '@/components/verso/media/media-api';
import { SeoPanel } from '@/components/verso/site/seo-panel';
import type { SeoAnalysisProps } from '@/components/verso/site/seo-panel';
import type { Block, EntrySeoFields } from '@/types';
import { libraryPage } from '../media/fixtures';

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

const analysis: SeoAnalysisProps = {
    config: { titleMax: 60, descriptionMax: 160, goodScore: 70, okScore: 40 },
    debounceMs: 300,
    keyphraseMaxLength: 100,
    site: {
        name: 'Harbor Lane Bakery',
        tagline: 'Bread from the old harbor',
        url: 'https://harborlane.example',
        host: 'harborlane.example',
        locale: 'en',
        titleFormat: '{title}{sep}{site}',
        separator: '|',
        defaultDescription: 'Fresh bread every morning since 1987.',
        defaultImage: 'https://harborlane.example/img/share.jpg',
        twitterSite: '@harborlane',
    },
};

const blocks: Block[] = [
    {
        id: 'b_intro1',
        type: 'paragraph',
        attrs: {},
        content:
            '<p>Our dark rye bread rests for a whole night. The slow rise gives the loaf a deep, sour taste.</p>',
    },
    {
        id: 'b_head01',
        type: 'heading',
        attrs: { level: 2 },
        content: 'Why the dough rests',
    },
    {
        id: 'b_body01',
        type: 'paragraph',
        attrs: {},
        content:
            '<p>Read <a href="/blog/sourdough-starter">how we feed the starter</a> and see the <a href="https://flour.example.org">mill we buy from</a>.</p>',
    },
];

function Panel({
    initial = empty,
    onChange = () => {},
}: {
    initial?: EntrySeoFields;
    onChange?: (value: EntrySeoFields) => void;
}) {
    const [value, setValue] = useState(initial);

    return (
        <SeoPanel
            value={value}
            onChange={(next) => {
                setValue(next);
                onChange(next);
            }}
            fallbackTitle="Dark rye bread that rests overnight"
            fallbackDescription={null}
            url="https://harborlane.example/blog/dark-rye-bread"
            slug="dark-rye-bread"
            blocks={blocks}
            featuredImage={null}
            analysis={analysis}
        />
    );
}

beforeEach(() => {
    vi.useFakeTimers();
    vi.mocked(fetchLibrary).mockResolvedValue(libraryPage([]));
});

afterEach(() => {
    vi.useRealTimers();
});

describe('SeoPanel analysis', () => {
    it('builds the Google title from the site title format', () => {
        render(<Panel />);

        expect(screen.getByTestId('snippet-title')).toHaveTextContent(
            'Dark rye bread that rests overnight | Harbor Lane Bakery',
        );
        expect(screen.getByTestId('snippet-description')).toHaveTextContent(
            'Fresh bread every morning since 1987.',
        );
    });

    it('shows the score and the checks', () => {
        render(<Panel />);

        expect(
            screen.getByRole('meter', { name: 'SEO score of this entry' }),
        ).toBeInTheDocument();
        expect(screen.getByText('SEO checks')).toBeInTheDocument();
        expect(
            document.querySelector('[data-check="keyphrase-set"]'),
        ).toHaveAttribute('data-status', 'problem');
    });

    it('does not change the entry when it opens', () => {
        const onChange = vi.fn();
        render(<Panel onChange={onChange} />);

        act(() => {
            vi.advanceTimersByTime(1000);
        });

        expect(onChange).not.toHaveBeenCalled();
    });

    it('runs the checks again after typing stops and saves the score', () => {
        const onChange = vi.fn();
        render(<Panel onChange={onChange} />);

        fireEvent.change(screen.getByLabelText('Focus keyphrase'), {
            target: { value: 'dark rye bread' },
        });

        expect(
            document.querySelector('[data-check="keyphrase-title"]'),
        ).toBeNull();

        act(() => {
            vi.advanceTimersByTime(300);
        });

        expect(
            document.querySelector('[data-check="keyphrase-title"]'),
        ).toHaveAttribute('data-status', 'good');
        expect(onChange).toHaveBeenLastCalledWith(
            expect.objectContaining({
                focus_keyphrase: 'dark rye bread',
                score: expect.any(Number),
            }),
        );
    });

    it('edits the share fields and nofollow', () => {
        const onChange = vi.fn();
        render(<Panel onChange={onChange} />);

        fireEvent.change(screen.getByLabelText('Share title'), {
            target: { value: 'Free rye bread on Saturday' },
        });
        fireEvent.click(
            screen.getByRole('checkbox', {
                name: /Do not follow links on this page/,
            }),
        );

        expect(onChange).toHaveBeenLastCalledWith(
            expect.objectContaining({
                og_title: 'Free rye bread on Saturday',
                nofollow: true,
            }),
        );
        expect(
            screen.getByRole('figure', { name: 'Share preview on Facebook' }),
        ).toHaveTextContent('Free rye bread on Saturday');
    });

    it('switches the previews to phone and X', () => {
        render(<Panel />);

        fireEvent.click(screen.getByRole('radio', { name: 'Phone' }));
        fireEvent.click(screen.getByRole('radio', { name: 'X' }));

        expect(
            screen.getByRole('figure', {
                name: 'Search result preview on a phone',
            }),
        ).toHaveAttribute('data-device', 'mobile');
        expect(
            screen.getByRole('figure', { name: 'Share preview on X' }),
        ).toHaveTextContent('From harborlane.example');
        expect(
            screen
                .getByRole('figure', { name: 'Share preview on X' })
                .querySelector('img'),
        ).toHaveAttribute('src', 'https://harborlane.example/img/share.jpg');
    });
});
