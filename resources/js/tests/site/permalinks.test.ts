import { describe, expect, it } from 'vitest';
import {
    buildPermalink,
    normalizePattern,
    patternProblem,
    patternTokens,
    presetFor,
    withTrailingSlash,
} from '@/lib/permalinks';

const tokens = ['slug', 'id', 'year', 'month', 'day', 'category'];
const sample = {
    slug: 'riding-tram-28',
    id: '418',
    year: '2026',
    month: '03',
    day: '09',
    category: 'travel',
};
const presets = {
    plain: '/{slug}',
    blog: '/blog/{slug}',
    month: '/{year}/{month}/{slug}',
    day: '/{year}/{month}/{day}/{slug}',
    category: '/{category}/{slug}',
    numeric: '/archives/{id}',
};

describe('normalizePattern', () => {
    it.each([
        ['blog/{slug}', '/blog/{slug}'],
        [' /blog//{slug}/ ', '/blog/{slug}'],
        ['', '/'],
    ])('turns %j into %j', (raw, normalized) => {
        expect(normalizePattern(raw)).toBe(normalized);
    });
});

describe('buildPermalink', () => {
    it.each([
        ['/blog/{slug}', '/blog/riding-tram-28'],
        ['/{year}/{month}/{slug}', '/2026/03/riding-tram-28'],
        ['/{year}/{month}/{day}/{slug}', '/2026/03/09/riding-tram-28'],
        ['/{category}/{slug}', '/travel/riding-tram-28'],
        ['/archives/{id}', '/archives/418'],
        ['/articles/{year}/{slug}', '/articles/2026/riding-tram-28'],
    ])('builds %s', (pattern, path) => {
        expect(buildPermalink(pattern, sample, false)).toBe(path);
    });

    it('adds the trailing slash when it is on', () => {
        expect(buildPermalink('/blog/{slug}', sample, true)).toBe(
            '/blog/riding-tram-28/',
        );
    });

    it('keeps unknown tokens visible', () => {
        expect(buildPermalink('/{author}/{slug}', sample, false)).toBe(
            '/{author}/riding-tram-28',
        );
    });
});

describe('withTrailingSlash', () => {
    it('never adds a slash after a file name or the root', () => {
        expect(withTrailingSlash('/notes/tram.html', true)).toBe(
            '/notes/tram.html',
        );
        expect(withTrailingSlash('/', true)).toBe('/');
        expect(withTrailingSlash('/blog', false)).toBe('/blog');
    });
});

describe('patternProblem', () => {
    it('accepts every preset', () => {
        for (const pattern of Object.values(presets)) {
            expect(patternProblem(pattern, false, tokens)).toBeNull();
        }
    });

    it.each([
        ['/blog/{author}/{slug}', false, 'Unknown token {author}.'],
        ['/{year}/{month}', false, 'The structure needs {slug} or {id}.'],
        ['/{slug}/{slug}', false, 'Use each token only once.'],
        ['/{year}{month}/{slug}', false, 'Put a slash between two tokens.'],
        [
            '/Blog/{slug}',
            false,
            'Use lowercase letters, numbers, dashes, dots, slashes and tokens only.',
        ],
        ['/pages/{id}', true, 'Nested entries need the {slug} token.'],
    ])('explains what is wrong with %s', (pattern, hierarchical, message) => {
        expect(patternProblem(pattern, hierarchical, tokens)).toBe(message);
    });
});

describe('presetFor', () => {
    it('finds the matching preset or custom', () => {
        expect(presetFor('/{year}/{month}/{slug}/', presets)).toBe('month');
        expect(presetFor('/articles/{slug}', presets)).toBe('custom');
    });

    it('lists tokens in order', () => {
        expect(patternTokens('/{year}/{slug}')).toEqual(['year', 'slug']);
    });
});
