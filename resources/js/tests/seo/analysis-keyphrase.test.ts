import { describe, expect, it } from 'vitest';
import { createKeyphraseMatcher } from '@/lib/seo-analysis';

describe('keyphrase matcher', () => {
    const bread = createKeyphraseMatcher('pan de masa madre', 'es');
    const tram = createKeyphraseMatcher('Lisbon tram ride', 'en');

    it('leaves out stop words', () => {
        expect(bread.words).toEqual(['pan', 'masa', 'madre']);
        expect(
            createKeyphraseMatcher('how to ride the tram', 'en').words,
        ).toEqual(['ride', 'tram']);
    });

    it('keeps stop words when the keyphrase has nothing else', () => {
        expect(createKeyphraseMatcher('the and', 'en').words).toEqual([
            'the',
            'and',
        ]);
    });

    it('is empty for a blank keyphrase', () => {
        const matcher = createKeyphraseMatcher('   ', 'en');

        expect(matcher.isEmpty).toBe(true);
        expect(matcher.matches('anything at all')).toBe(false);
        expect(matcher.count('anything at all')).toBe(0);
    });

    it('ignores case and accents', () => {
        expect(bread.matches('Receta de PAN DE MASA MADRE')).toBe(true);
        expect(
            createKeyphraseMatcher('cómo hornear', 'es').matches(
                'Como HORNEAR sin horno',
            ),
        ).toBe(true);
        expect(
            createKeyphraseMatcher('graca viewpoint', 'en').matches(
                'The Graça viewpoint',
            ),
        ).toBe(true);
    });

    it('matches words in any order and with words in between', () => {
        expect(bread.matches('Una madre que hace masa para el pan')).toBe(true);
        expect(tram.matches('Ride the old tram across Lisbon')).toBe(true);
    });

    it('needs every content word', () => {
        expect(bread.matches('Pan con masa fina')).toBe(false);
        expect(tram.matches('The Lisbon tram museum')).toBe(false);
    });

    it('accepts simple English plurals and possessives', () => {
        expect(tram.matches("Lisbon's trams make every ride fun")).toBe(true);
        expect(
            createKeyphraseMatcher('city', 'en').matches('Two cities by train'),
        ).toBe(true);
        expect(
            createKeyphraseMatcher('custard tarts', 'en').matches(
                'A warm custard tart',
            ),
        ).toBe(true);
        expect(
            createKeyphraseMatcher('bus pass', 'en').matches(
                'Buses and passes',
            ),
        ).toBe(true);
    });

    it('accepts simple Spanish plurals', () => {
        expect(bread.matches('Panes de masas madres caseras')).toBe(true);
        expect(
            createKeyphraseMatcher('luz natural', 'es').matches(
                'Las luces naturales',
            ),
        ).toBe(true);
        expect(
            createKeyphraseMatcher('ciudad', 'es').matches('Tres ciudades'),
        ).toBe(true);
    });

    it('does not match a word that only contains the keyword', () => {
        expect(
            createKeyphraseMatcher('pan', 'es').matches(
                'La panadería abre pronto',
            ),
        ).toBe(false);
    });

    it('counts full keyphrase matches in each sentence', () => {
        expect(
            bread.count(
                'Hoy hacemos pan de masa madre. El pan necesita tiempo. La masa madre y el pan, otra vez pan de masa madre.',
            ),
        ).toBe(3);
        expect(
            createKeyphraseMatcher('tram', 'en').count(
                'Tram after tram. No trams at night.',
            ),
        ).toBe(3);
    });
});
