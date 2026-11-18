import { describe, expect, it } from 'vitest';
import type { AnalysisConfig, CheckResult } from '@/lib/seo-analysis';
import { analyze } from '@/lib/seo-analysis';
import type { Block } from '@/types';
import { BREAD_INPUT, TRAM_INPUT, heading, paragraph } from './fixtures/posts';

function checkOf(
    id: string,
    blocks: Block[],
    locale = 'en',
    config: Partial<AnalysisConfig> = {},
): CheckResult | undefined {
    return analyze({ ...TRAM_INPUT, blocks, locale }, config).checks.find(
        (check) => check.id === id,
    );
}

const SHORT_SENTENCE = 'The tram stops near the castle.';
const LONG_SENTENCE =
    'The old yellow tram climbs slowly from Martim Moniz through the narrow streets of Graça and Alfama before it finally reaches the busy square at Chiado.';

// Varied sentences that make a text long enough for the readability checks.
const CITY_MORNING =
    'Lisbon wakes up early. Bakers open their doors at six. Fishermen bring sardines to the market. Students crowd the steps of the old university. Cafés fill with the smell of coffee. Musicians tune their guitars in Alfama. Taxis honk on Avenida da Liberdade. Children run to school with heavy bags. Evening brings soft light over the river.';

function sentences(count: number, sentence = SHORT_SENTENCE): string {
    return Array.from({ length: count }, () => sentence).join(' ');
}

describe('sentence-length', () => {
    it('is good when few sentences are long', () => {
        expect(
            checkOf('sentence-length', [paragraph(sentences(10))])?.status,
        ).toBe('good');
    });

    it('is ok when slightly over the limit', () => {
        const text = `${sentences(18)} ${sentences(7, LONG_SENTENCE)}`;

        expect(checkOf('sentence-length', [paragraph(text)])?.status).toBe(
            'ok',
        );
    });

    it('is a problem when many sentences are long', () => {
        const text = `${sentences(4)} ${sentences(4, LONG_SENTENCE)}`;
        const check = checkOf('sentence-length', [paragraph(text)]);

        expect(check?.status).toBe('problem');
        expect(check?.message).toContain('50%');
    });

    it('follows the sentenceMaxWords setting', () => {
        expect(
            checkOf(
                'sentence-length',
                [paragraph(sentences(4, LONG_SENTENCE))],
                'en',
                {
                    sentenceMaxWords: 40,
                },
            )?.status,
        ).toBe('good');
    });

    it('is skipped without sentences', () => {
        expect(
            checkOf('sentence-length', [heading('Only a heading')]),
        ).toBeUndefined();
    });
});

describe('very short texts', () => {
    it('skip the sentence based checks', () => {
        const ids = analyze({
            ...TRAM_INPUT,
            blocks: [
                paragraph(
                    'The tram was built in 1914. The tram is old. The tram is slow.',
                ),
            ],
        }).checks.map((check) => check.id);

        expect(ids).not.toContain('sentence-length');
        expect(ids).not.toContain('paragraph-length');
        expect(ids).not.toContain('passive-voice');
        expect(ids).not.toContain('consecutive-sentences');
    });
});

describe('paragraph-length', () => {
    it('is good for short paragraphs', () => {
        expect(
            checkOf('paragraph-length', [
                paragraph(sentences(5)),
                paragraph(sentences(5)),
            ])?.status,
        ).toBe('good');
    });

    it('is ok just over the limit and a problem far over it', () => {
        expect(
            checkOf('paragraph-length', [paragraph(sentences(26))])?.status,
        ).toBe('ok');
        expect(
            checkOf('paragraph-length', [paragraph(sentences(40))])?.status,
        ).toBe('problem');
    });
});

describe('subheading-distribution', () => {
    it('is skipped for short texts', () => {
        expect(
            checkOf('subheading-distribution', [paragraph(sentences(10))]),
        ).toBeUndefined();
    });

    it('is a problem for a long text without subheadings', () => {
        const blocks = Array.from({ length: 10 }, () =>
            paragraph(sentences(6)),
        );

        expect(checkOf('subheading-distribution', blocks)?.status).toBe(
            'problem',
        );
    });

    it('is good when subheadings split the text', () => {
        const blocks = Array.from({ length: 10 }, (_, index) => [
            heading(`Stop number ${index + 1}`),
            paragraph(sentences(6)),
        ]).flat();

        expect(checkOf('subheading-distribution', blocks)?.status).toBe('good');
    });

    it('is ok when one part is a little too long', () => {
        const blocks = [
            heading('Morning'),
            ...Array.from({ length: 7 }, () => paragraph(sentences(8))),
            heading('Evening'),
            paragraph(sentences(4)),
        ];

        expect(checkOf('subheading-distribution', blocks)?.status).toBe('ok');
    });
});

describe('heading-structure', () => {
    it('is good for H2 then H3', () => {
        expect(
            checkOf('heading-structure', [
                heading('Tickets'),
                heading('Prices', 3),
            ])?.status,
        ).toBe('good');
    });

    it('is ok when a level is skipped', () => {
        const check = checkOf('heading-structure', [
            heading('Tickets'),
            heading('Prices', 4),
        ]);

        expect(check?.status).toBe('ok');
        expect(check?.message).toBe(
            'An H4 comes after an H2. Do not skip heading levels.',
        );
    });

    it('is ok when the first heading is an H3', () => {
        expect(
            checkOf('heading-structure', [heading('Prices', 3)])?.status,
        ).toBe('ok');
    });

    it('is skipped without headings', () => {
        expect(
            checkOf('heading-structure', [paragraph(SHORT_SENTENCE)]),
        ).toBeUndefined();
    });
});

describe('passive-voice', () => {
    it('finds regular and irregular passive sentences', () => {
        const text = [
            'The tram line was opened in 1914.',
            'The cars are still built by hand.',
            'Tickets are not sold on board.',
            'The driver rings the bell.',
        ].join(' ');
        const check = checkOf('passive-voice', [
            paragraph(`${text} ${text} ${text}`),
        ]);

        expect(check?.status).toBe('problem');
        expect(check?.message).toContain('75%');
    });

    it('is good for active sentences and ignores words like "need" or "red"', () => {
        const text =
            'The tram is red. We need a ticket. The driver is friendly. Tourists love the view.';

        expect(
            checkOf('passive-voice', [paragraph(text), paragraph(CITY_MORNING)])
                ?.status,
        ).toBe('good');
    });

    it('is ok when slightly over the limit', () => {
        const text = `The bell was rung twice. ${sentences(7, 'Tourists from many countries ride the yellow tram every day.')}`;

        expect(checkOf('passive-voice', [paragraph(text)])?.status).toBe('ok');
    });

    it('only runs for English', () => {
        expect(
            checkOf(
                'passive-voice',
                [paragraph('El pan fue horneado ayer.')],
                'es',
            ),
        ).toBeUndefined();
        expect(
            analyze(BREAD_INPUT).checks.some(
                (check) => check.id === 'passive-voice',
            ),
        ).toBe(false);
        expect(
            checkOf('passive-voice', [paragraph(SHORT_SENTENCE)], 'pt_PT'),
        ).toBeUndefined();
    });
});

describe('transition-words', () => {
    it('is good with many English transition words, including phrases', () => {
        const text = [
            'First, buy a ticket.',
            'For example, a day pass is cheap.',
            'However, the line is long.',
            'In addition, the cars are small.',
            'The view is lovely.',
        ].join(' ');

        expect(checkOf('transition-words', [paragraph(text)])?.status).toBe(
            'good',
        );
    });

    it('is a problem with none', () => {
        expect(
            checkOf('transition-words', [paragraph(sentences(6))])?.status,
        ).toBe('problem');
    });

    it('is ok when a bit under the target', () => {
        const text = `However, the tram was late. ${sentences(4)}`;

        expect(checkOf('transition-words', [paragraph(text)])?.status).toBe(
            'ok',
        );
    });

    it('knows Spanish words and phrases with or without accents', () => {
        const text = [
            'Sin embargo, la masa tarda.',
            'Además, hace frío.',
            'Por ejemplo, en invierno.',
            'Luego se hornea.',
            'El pan sale rico.',
        ].join(' ');

        expect(
            checkOf('transition-words', [paragraph(text)], 'es')?.status,
        ).toBe('good');
    });

    it('is skipped for short texts and other languages', () => {
        expect(
            checkOf('transition-words', [paragraph(sentences(3))]),
        ).toBeUndefined();
        expect(
            checkOf('transition-words', [paragraph(sentences(8))], 'pt'),
        ).toBeUndefined();
    });
});

describe('consecutive-sentences', () => {
    it('is ok when three sentences in a row start the same way', () => {
        const text =
            'The tram is old. The seats are wooden. The bell is loud. Lisbon is lovely.';
        const check = checkOf('consecutive-sentences', [
            paragraph(CITY_MORNING),
            paragraph(text),
        ]);

        expect(check?.status).toBe('ok');
        expect(check?.message).toContain('"the"');
    });

    it('is good when sentences start in different ways', () => {
        expect(
            checkOf('consecutive-sentences', [
                paragraph(CITY_MORNING),
                paragraph(
                    'The tram is old. Its seats are wooden. A bell rings.',
                ),
            ])?.status,
        ).toBe('good');
    });
});
