import { describe, expect, it } from 'vitest';
import type {
    AnalysisConfig,
    AnalysisInput,
    CheckResult,
} from '@/lib/seo-analysis';
import { analyze } from '@/lib/seo-analysis';
import {
    BREAD_INPUT,
    TRAM_BLOCKS,
    TRAM_INPUT,
    heading,
    image,
    paragraph,
} from './fixtures/posts';

function checkOf(
    id: string,
    changes: Partial<AnalysisInput> = {},
    config: Partial<AnalysisConfig> = {},
): CheckResult | undefined {
    return analyze({ ...TRAM_INPUT, ...changes }, config).checks.find(
        (check) => check.id === id,
    );
}

const statusOf = (
    id: string,
    changes: Partial<AnalysisInput> = {},
    config: Partial<AnalysisConfig> = {},
) => checkOf(id, changes, config)?.status;

// Repeats a sentence until the text has about this many words.
function filler(words: number): string {
    const sentence =
        'Locals buy fresh bread at the corner bakery every morning.';
    const perSentence = 10;

    return Array.from(
        { length: Math.ceil(words / perSentence) },
        () => sentence,
    ).join(' ');
}

describe('keyphrase checks', () => {
    it('asks for a keyphrase and hides keyphrase checks when it is empty', () => {
        const result = analyze({ ...TRAM_INPUT, keyphrase: '' });
        const ids = result.checks.map((check) => check.id);

        expect(
            result.checks.find((check) => check.id === 'keyphrase-set'),
        ).toMatchObject({
            status: 'problem',
            message: 'Set a focus keyphrase to get keyphrase tips.',
        });
        expect(ids).not.toContain('keyphrase-title');
        expect(ids).not.toContain('keyphrase-density');
    });

    it('is good when the keyphrase is set', () => {
        expect(statusOf('keyphrase-set')).toBe('good');
    });

    it('warns about long keyphrases', () => {
        expect(statusOf('keyphrase-length')).toBe('good');
        expect(
            statusOf('keyphrase-length', {
                keyphrase: 'best yellow vintage tram ride Lisbon',
            }),
        ).toBe('ok');
    });

    it('checks the SEO title', () => {
        expect(checkOf('keyphrase-title')).toMatchObject({
            status: 'good',
            message: 'The focus keyphrase is in the SEO title. Well done.',
        });
        expect(
            checkOf('keyphrase-title', {
                seoTitle: 'A day in Alfama | Harbor Lane',
            }),
        ).toMatchObject({
            status: 'problem',
            message: 'Add the focus keyphrase to the SEO title.',
        });
    });

    it('checks the first paragraph', () => {
        expect(statusOf('keyphrase-intro')).toBe('good');
        expect(
            statusOf('keyphrase-intro', {
                blocks: [
                    paragraph('Lisbon is a city of hills and light.'),
                    ...TRAM_BLOCKS,
                ],
            }),
        ).toBe('problem');
        expect(statusOf('keyphrase-intro', { blocks: [] })).toBe('problem');
    });

    it('checks the slug with dashes as spaces', () => {
        expect(statusOf('keyphrase-slug')).toBe('good');
        expect(statusOf('keyphrase-slug', { slug: 'lisbon-yellow-cars' })).toBe(
            'ok',
        );
    });

    it('checks the meta description', () => {
        expect(statusOf('keyphrase-description')).toBe('good');
        expect(
            statusOf('keyphrase-description', {
                description: 'A slow ride through the old town of Lisbon.',
            }),
        ).toBe('problem');
        expect(statusOf('keyphrase-description', { description: '' })).toBe(
            'problem',
        );
    });

    it('checks H2 and H3 subheadings', () => {
        expect(statusOf('keyphrase-headings')).toBe('good');
        expect(
            statusOf('keyphrase-headings', {
                blocks: [
                    paragraph('Tram 28 is yellow.'),
                    heading('Practical tips'),
                ],
            }),
        ).toBe('problem');
        expect(
            statusOf('keyphrase-headings', {
                blocks: [
                    paragraph('Tram 28 is yellow.'),
                    heading('Tram 28 at night', 4),
                ],
            }),
        ).toBe('ok');
        expect(
            statusOf('keyphrase-headings', {
                blocks: [paragraph('Tram 28 is yellow.')],
            }),
        ).toBe('ok');
    });

    it('checks the keyphrase density', () => {
        expect(statusOf('keyphrase-density')).toBe('good');
        expect(
            statusOf('keyphrase-density', { blocks: [paragraph(filler(300))] }),
        ).toBe('problem');
        expect(
            statusOf('keyphrase-density', {
                blocks: [
                    paragraph(`${'Tram 28 is fun. '.repeat(10)}${filler(100)}`),
                ],
            }),
        ).toBe('problem');
        expect(
            checkOf('keyphrase-density', { blocks: [paragraph(filler(300))] })
                ?.message,
        ).toContain('0 times');
    });

    it('skips the density check for short texts', () => {
        expect(
            checkOf('keyphrase-density', {
                blocks: [paragraph('Tram 28 is fun.')],
            }),
        ).toBeUndefined();
    });
});

describe('title and description length', () => {
    it('rates the SEO title length', () => {
        expect(statusOf('title-length')).toBe('good');
        expect(statusOf('title-length', { seoTitle: '' })).toBe('problem');
        expect(
            statusOf('title-length', { seoTitle: 'Tram 28 in Lisbon guide' }),
        ).toBe('ok');
        expect(statusOf('title-length', { seoTitle: 'Tram 28' })).toBe(
            'problem',
        );
        expect(
            statusOf('title-length', {
                seoTitle:
                    'Riding Tram 28 in Lisbon: the complete guide for 2026 | Harbor',
            }),
        ).toBe('ok');
        expect(
            statusOf('title-length', { seoTitle: 'Tram 28 '.repeat(12) }),
        ).toBe('problem');
    });

    it('rates the meta description length', () => {
        expect(statusOf('description-length')).toBe('good');
        expect(statusOf('description-length', { description: '' })).toBe(
            'problem',
        );
        expect(
            statusOf('description-length', {
                description: 'Ride Tram 28 early.',
            }),
        ).toBe('ok');
        expect(
            statusOf('description-length', {
                description: 'Tram 28 is a great ride. '.repeat(8),
            }),
        ).toBe('ok');
    });

    it('uses config overrides for limits', () => {
        expect(
            statusOf(
                'title-length',
                { seoTitle: 'Tram 28 in Lisbon guide' },
                { titleMin: 20 },
            ),
        ).toBe('good');
    });
});

describe('content checks', () => {
    it('rates the text length', () => {
        expect(checkOf('text-length')).toMatchObject({ status: 'good' });
        expect(
            statusOf('text-length', { blocks: [paragraph(filler(180))] }),
        ).toBe('ok');
        expect(
            statusOf('text-length', { blocks: [paragraph(filler(60))] }),
        ).toBe('problem');
        expect(
            statusOf(
                'text-length',
                { blocks: [paragraph(filler(60))] },
                { minWords: 50 },
            ),
        ).toBe('good');
    });

    it('needs internal links', () => {
        expect(statusOf('internal-links')).toBe('good');
        expect(
            statusOf('internal-links', {
                blocks: [
                    paragraph(
                        'See <a href="https://www.carris.pt/">Carris</a>.',
                    ),
                ],
            }),
        ).toBe('problem');
    });

    it('likes outbound links that are not all nofollow', () => {
        expect(statusOf('outbound-links')).toBe('good');
        expect(
            statusOf('outbound-links', {
                blocks: [paragraph('Read <a href="/blog">our blog</a>.')],
            }),
        ).toBe('ok');
        expect(
            statusOf('outbound-links', {
                blocks: [
                    paragraph(
                        'Book <a href="https://booking.example.com/" rel="nofollow">a hotel</a>.',
                    ),
                ],
            }),
        ).toBe('ok');
    });

    it('checks image alt text', () => {
        expect(checkOf('image-alt')?.message).toContain(
            'one uses the focus keyphrase',
        );
        expect(
            statusOf('image-alt', {
                blocks: [image('/img/a.jpg', ''), image('/img/b.jpg', 'Graça')],
            }),
        ).toBe('problem');
        expect(
            checkOf('image-alt', { blocks: [paragraph('No pictures here.')] }),
        ).toBeUndefined();
        expect(
            checkOf('image-alt', {
                blocks: [image('/img/b.jpg', 'View over Alfama')],
            }),
        ).toMatchObject({
            status: 'good',
            message:
                'All images have alt text. You can also use the focus keyphrase in one of them.',
        });
        expect(
            checkOf('image-alt', {
                keyphrase: '',
                blocks: [image('/img/b.jpg', 'View over Alfama')],
            })?.message,
        ).toBe('All images have alt text. Well done.');
    });

    it('asks for an image when there is none', () => {
        expect(statusOf('images')).toBe('good');
        expect(
            statusOf('images', {
                blocks: [paragraph('Just words.')],
                hasFeaturedImage: true,
            }),
        ).toBe('good');
        expect(
            statusOf('images', {
                blocks: [paragraph('Just words.')],
                hasFeaturedImage: false,
            }),
        ).toBe('ok');
    });

    it('checks the slug length', () => {
        expect(statusOf('slug-length')).toBe('good');
        expect(
            statusOf('slug-length', {
                slug: 'how-to-ride-tram-28-in-lisbon-without-crowds',
            }),
        ).toBe('ok');
        expect(checkOf('slug-length', { slug: '' })).toBeUndefined();
    });

    it('flags an H1 inside the content', () => {
        expect(statusOf('single-h1')).toBe('good');
        expect(
            checkOf('single-h1', {
                blocks: [heading('Riding Tram 28', 1), ...TRAM_BLOCKS],
            }),
        ).toMatchObject({
            status: 'problem',
            message: 'The page title is already the H1. Use H2 for sections.',
        });
    });
});

describe('Spanish post', () => {
    it('finds the keyphrase with accents and stop words', () => {
        const result = analyze(BREAD_INPUT);
        const status = (id: string) =>
            result.checks.find((check) => check.id === id)?.status;

        expect(result.language).toBe('es');
        expect(status('keyphrase-title')).toBe('good');
        expect(status('keyphrase-intro')).toBe('good');
        expect(status('keyphrase-slug')).toBe('good');
        expect(status('keyphrase-headings')).toBe('good');
        expect(status('images')).toBe('good');
    });
});
