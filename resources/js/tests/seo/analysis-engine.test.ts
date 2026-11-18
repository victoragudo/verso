import { afterEach, describe, expect, it } from 'vitest';
import type { AnalysisCheck } from '@/lib/seo-analysis';
import {
    DEFAULT_ANALYSIS_CONFIG,
    analysisChecks,
    analyze,
    ratingOf,
    registerAnalysisCheck,
    resetAnalysisChecks,
    unregisterAnalysisCheck,
} from '@/lib/seo-analysis';
import { BREAD_INPUT, TRAM_INPUT, longPost, paragraph } from './fixtures/posts';

const CORE_IDS = [
    'keyphrase-set',
    'keyphrase-length',
    'keyphrase-title',
    'keyphrase-intro',
    'keyphrase-slug',
    'keyphrase-description',
    'keyphrase-headings',
    'keyphrase-density',
    'title-length',
    'description-length',
    'text-length',
    'internal-links',
    'outbound-links',
    'image-alt',
    'images',
    'slug-length',
    'single-h1',
    'sentence-length',
    'paragraph-length',
    'subheading-distribution',
    'heading-structure',
    'passive-voice',
    'transition-words',
    'consecutive-sentences',
];

const affiliateCheck: AnalysisCheck = {
    id: 'affiliate-disclosure',
    group: 'seo',
    label: 'Affiliate disclosure',
    run: ({ content }) =>
        content.text.includes('affiliate')
            ? {
                  status: 'good',
                  message: 'You tell readers about affiliate links.',
              }
            : {
                  status: 'problem',
                  message: 'Tell readers that you earn from affiliate links.',
              },
};

afterEach(() => {
    resetAnalysisChecks();
});

describe('registry', () => {
    it('starts with the core checks in order', () => {
        expect(analysisChecks().map((check) => check.id)).toEqual(CORE_IDS);
    });

    it('adds a plugin check that shows up in the result and the score', () => {
        const before = analyze(TRAM_INPUT);
        registerAnalysisCheck(affiliateCheck);
        const after = analyze(TRAM_INPUT);

        expect(after.checks[0]).toEqual({
            id: 'affiliate-disclosure',
            group: 'seo',
            status: 'problem',
            label: 'Affiliate disclosure',
            message: 'Tell readers that you earn from affiliate links.',
        });
        expect(after.seoScore).toBeLessThan(before.seoScore);
    });

    it('replaces a check with the same id in place', () => {
        registerAnalysisCheck({
            id: 'images',
            group: 'seo',
            label: 'Images (plugin)',
            run: () => ({
                status: 'ok',
                message: 'Our plugin checks images now.',
            }),
        });

        const ids = analysisChecks().map((check) => check.id);
        expect(ids).toEqual(CORE_IDS);
        expect(
            analyze(TRAM_INPUT).checks.find((check) => check.id === 'images')
                ?.message,
        ).toBe('Our plugin checks images now.');
    });

    it('removes a check and resets back to the core list', () => {
        unregisterAnalysisCheck('passive-voice');
        registerAnalysisCheck(affiliateCheck);

        expect(analysisChecks().map((check) => check.id)).not.toContain(
            'passive-voice',
        );
        expect(
            analyze(TRAM_INPUT).checks.some(
                (check) => check.id === 'passive-voice',
            ),
        ).toBe(false);

        resetAnalysisChecks();
        expect(analysisChecks().map((check) => check.id)).toEqual(CORE_IDS);
    });

    it('respects needsKeyphrase and languages on plugin checks', () => {
        registerAnalysisCheck({
            ...affiliateCheck,
            id: 'spanish-only',
            languages: ['es'],
        });
        registerAnalysisCheck({
            ...affiliateCheck,
            id: 'needs-keyphrase',
            needsKeyphrase: true,
        });

        const english = analyze({ ...TRAM_INPUT, keyphrase: '' }).checks.map(
            (check) => check.id,
        );
        const spanish = analyze(BREAD_INPUT).checks.map((check) => check.id);

        expect(english).not.toContain('spanish-only');
        expect(english).not.toContain('needs-keyphrase');
        expect(spanish).toContain('spanish-only');
        expect(spanish).toContain('needs-keyphrase');
    });

    it('skips a plugin check that returns null or throws', () => {
        registerAnalysisCheck({
            ...affiliateCheck,
            id: 'not-applicable',
            run: () => null,
        });
        registerAnalysisCheck({
            ...affiliateCheck,
            id: 'broken',
            run: () => {
                throw new Error('Plugin bug');
            },
        });

        const ids = analyze(TRAM_INPUT).checks.map((check) => check.id);

        expect(ids).not.toContain('not-applicable');
        expect(ids).not.toContain('broken');
    });

    it('uses check weights in the group score', () => {
        resetAnalysisChecks();
        analysisChecks().forEach((check) => unregisterAnalysisCheck(check.id));
        registerAnalysisCheck({
            id: 'heavy-good',
            group: 'seo',
            label: 'Heavy',
            weight: 3,
            run: () => ({ status: 'good', message: 'Fine.' }),
        });
        registerAnalysisCheck({
            id: 'light-problem',
            group: 'seo',
            label: 'Light',
            run: () => ({ status: 'problem', message: 'Fix it.' }),
        });

        const result = analyze(TRAM_INPUT);

        expect(result.seoScore).toBe(75);
        expect(result.readabilityScore).toBe(0);
        expect(result.score).toBe(75);
    });
});

describe('scoring', () => {
    it('gives a well written English post a good rating', () => {
        const result = analyze(TRAM_INPUT);

        expect(result.seoScore).toBe(100);
        expect(result.readabilityScore).toBe(100);
        expect(result.score).toBe(100);
        expect(result.rating).toBe('good');
        expect(result.language).toBe('en');
        expect(result.wordCount).toBeGreaterThan(300);
    });

    it('counts ok as half a point', () => {
        resetAnalysisChecks();
        analysisChecks().forEach((check) => unregisterAnalysisCheck(check.id));
        registerAnalysisCheck({
            id: 'half',
            group: 'readability',
            label: 'Half',
            run: () => ({ status: 'ok', message: 'Almost.' }),
        });
        registerAnalysisCheck({
            id: 'full',
            group: 'readability',
            label: 'Full',
            run: () => ({ status: 'good', message: 'Great.' }),
        });

        expect(analyze(TRAM_INPUT).readabilityScore).toBe(75);
    });

    it('sorts problems first, then ok, then good, keeping the order inside', () => {
        const statuses = analyze({
            ...TRAM_INPUT,
            description: '',
            keyphrase: '',
        }).checks.map((check) => check.status);
        const rank = { problem: 0, ok: 1, good: 2 };
        const ranks = statuses.map((status) => rank[status]);

        expect(ranks).toEqual([...ranks].sort((a, b) => a - b));
        expect(statuses[0]).toBe('problem');
    });

    it('rates a short draft from its SEO checks only', () => {
        const result = analyze({
            ...TRAM_INPUT,
            seoTitle: 'Draft',
            description: '',
            slug: 'draft',
            blocks: [paragraph('Some notes about the tram for later.')],
            hasFeaturedImage: false,
        });

        expect(result.readabilityScore).toBe(0);
        expect(result.score).toBe(result.seoScore);
        expect(result.rating).toBe('ok');
    });

    it('rates an entry with almost nothing as poor', () => {
        const result = analyze({
            ...TRAM_INPUT,
            seoTitle: 'Tram',
            keyphrase: '',
            description: '',
            slug: 'notes',
            blocks: [paragraph('Notes for later.')],
            hasFeaturedImage: false,
        });

        expect(result.rating).toBe('poor');
    });

    it('rates an empty entry as none', () => {
        const result = analyze({
            ...TRAM_INPUT,
            title: '',
            seoTitle: '',
            description: '',
            slug: '',
            keyphrase: '',
            blocks: [],
        });

        expect(result.rating).toBe('none');
        expect(result.wordCount).toBe(0);
    });

    it('rates an entry with only a title', () => {
        expect(analyze({ ...TRAM_INPUT, blocks: [] }).rating).not.toBe('none');
    });

    it('applies config overrides', () => {
        const strict = analyze(TRAM_INPUT, { minWords: 600 });

        expect(
            strict.checks.find((check) => check.id === 'text-length')?.status,
        ).toBe('ok');
        expect(DEFAULT_ANALYSIS_CONFIG.minWords).toBe(300);
    });
});

describe('ratingOf', () => {
    it.each([
        [100, 'good'],
        [70, 'good'],
        [69, 'ok'],
        [40, 'ok'],
        [39, 'poor'],
        [0, 'poor'],
    ] as const)('rates %d as %s', (score, rating) => {
        expect(ratingOf(score, DEFAULT_ANALYSIS_CONFIG)).toBe(rating);
    });

    it('uses custom thresholds', () => {
        expect(ratingOf(65, { goodScore: 60, okScore: 30 })).toBe('good');
    });
});

describe('speed', () => {
    it('analyzes a 3000 word post quickly', () => {
        const input = { ...TRAM_INPUT, blocks: longPost(32) };
        analyze(input);

        const start = performance.now();
        const result = analyze(input);
        const elapsed = performance.now() - start;

        expect(result.wordCount).toBeGreaterThan(2900);
        expect(elapsed).toBeLessThan(150);
    });
});
