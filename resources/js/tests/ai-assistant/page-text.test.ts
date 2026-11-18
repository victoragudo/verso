import { describe, expect, it } from 'vitest';
import { t } from '@/lib/i18n';
import { AiRequestError } from '@/pages/admin/plugins/ai-assistant/ai-api';
import {
    changedTexts,
    escapeHtml,
    firstHeading,
    friendlyError,
    HELPER_ERRORS,
    imagesWithoutAlt,
    sectionHasText,
    sectionWords,
    topSection,
    withAltTexts,
    withHeadline,
    withSection,
} from '@/pages/admin/plugins/ai-assistant/page-helper/page-text';
import type { Block } from '@/types';
import {
    bakeryPage,
    gallerySection,
    heroSection,
} from './page-helper-fixtures';

describe('what a section holds', () => {
    it('finds text in headings, paragraphs and button labels', () => {
        expect(sectionHasText(heroSection())).toBe(true);
        expect(sectionHasText(gallerySection())).toBe(false);
    });

    it('finds the first heading of a section and of the page', () => {
        expect(firstHeading(heroSection())).toEqual({
            id: 'b_title001',
            text: 'Welcome to our shop',
        });
        expect(firstHeading(gallerySection())).toBeNull();
        expect(topSection([gallerySection(), heroSection()])?.id).toBe(
            'b_hero0001',
        );
        expect(topSection([gallerySection()])).toBeNull();
    });

    it('reads the words of a section without tags', () => {
        expect(sectionWords(heroSection())).toBe(
            'Welcome to our shop\nWe sell good things .',
        );
    });
});

describe('pictures without a description', () => {
    it('lists image blocks and gallery pictures that have no alt text', () => {
        expect(imagesWithoutAlt(heroSection())).toEqual([
            {
                key: 'b_photo001',
                blockId: 'b_photo001',
                mediaId: 7,
                src: '/storage/loaves.jpg',
            },
        ]);
        expect(
            imagesWithoutAlt(gallerySection()).map((spot) => spot.key),
        ).toEqual(['b_gallery1:0', 'b_gallery1:2']);
    });

    it('skips pictures that are not in the media library', () => {
        const section: Block = {
            ...heroSection(),
            children: [
                {
                    id: 'b_photo009',
                    type: 'image',
                    attrs: { src: 'https://cdn.example.org/harbor.jpg' },
                    children: [],
                },
            ],
        };

        expect(imagesWithoutAlt(section)).toEqual([]);
    });

    it('sets the chosen descriptions and keeps the other pictures', () => {
        const page = bakeryPage();
        const [buns, coffee] = imagesWithoutAlt(gallerySection());
        const next = withAltTexts(page, [
            {
                ...imagesWithoutAlt(heroSection())[0],
                alt: 'Sourdough loaves on a wooden board',
            },
            { ...coffee, alt: 'A flat white with a heart in the foam' },
        ]);

        const images = next[1].children![0].attrs.images as {
            alt?: string;
        }[];

        expect(next[0].children![3].attrs.alt).toBe(
            'Sourdough loaves on a wooden board',
        );
        expect(images.map((image) => image.alt)).toEqual([
            '',
            'The stone oven at dawn',
            'A flat white with a heart in the foam',
        ]);
        expect(buns.index).toBe(0);
        expect(page[0].children![3].attrs.alt).toBe('');
    });
});

describe('changes and how to apply them', () => {
    it('lists every changed text by block id, in reading order', () => {
        const before = heroSection();
        const after: Block = {
            ...before,
            children: [
                { ...before.children![0], content: 'Sourdough by the harbor' },
                before.children![1],
                {
                    ...before.children![2],
                    attrs: {
                        ...before.children![2].attrs,
                        label: 'See the bread',
                    },
                },
                before.children![3],
            ],
        };

        expect(changedTexts([before], [after])).toEqual([
            {
                key: 'b_title001:content',
                before: 'Welcome to our shop',
                after: 'Sourdough by the harbor',
            },
            {
                key: 'b_button01:label',
                before: 'Learn more',
                after: 'See the bread',
            },
        ]);
    });

    it('ignores changes in tags only', () => {
        const before = heroSection();
        const after: Block = {
            ...before,
            children: [
                {
                    ...before.children![0],
                    content: '<strong>Welcome to our shop</strong>',
                },
            ],
        };

        expect(changedTexts([before], [after])).toEqual([]);
    });

    it('swaps one section and keeps the others', () => {
        const page = bakeryPage();
        const written = { ...heroSection(), attrs: { label: 'Hello' } };
        const next = withSection(page, written);

        expect(next[0]).toBe(written);
        expect(next[1]).toBe(page[1]);
    });

    it('puts a headline in the heading as safe text', () => {
        const next = withHeadline(
            bakeryPage(),
            'b_title001',
            '  Bread & butter <daily>  ',
        );

        expect(next[0].children![0].content).toBe(
            'Bread &amp; butter &lt;daily&gt;',
        );
        expect(escapeHtml('a < b')).toBe('a &lt; b');
    });
});

describe('plain error messages', () => {
    it('keeps the server message for checks and rate limits', () => {
        expect(
            friendlyError(
                new AiRequestError(
                    'You have used all your AI requests for now. Try again in 12 min.',
                    429,
                ),
            ),
        ).toBe(
            'You have used all your AI requests for now. Try again in 12 min.',
        );
        expect(
            friendlyError(
                new AiRequestError(
                    'This section has no text to write yet.',
                    422,
                ),
            ),
        ).toBe('This section has no text to write yet.');
    });

    it('turns provider and network failures into plain words', () => {
        expect(
            friendlyError(
                new AiRequestError('The provider rejected the API key.', 502),
            ),
        ).toBe(t(HELPER_ERRORS.busy));
        expect(friendlyError(new AiRequestError('Timeout', 504))).toBe(
            'The AI helper is busy. Try again in a minute.',
        );
        expect(friendlyError(new AiRequestError('', 0))).toBe(
            t(HELPER_ERRORS.offline),
        );
        expect(friendlyError(new AiRequestError('', 503))).toBe(
            t(HELPER_ERRORS.notReady),
        );
        expect(friendlyError(new AiRequestError('', 403))).toBe(
            t(HELPER_ERRORS.forbidden),
        );
        expect(friendlyError(new Error('boom'))).toBe(t(HELPER_ERRORS.busy));
    });
});
