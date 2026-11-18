import { describe, expect, it } from 'vitest';
import {
    htmlToText,
    languageOf,
    normalize,
    splitSentences,
    wordsOf,
} from '@/lib/seo-analysis';

describe('wordsOf', () => {
    it('counts English words with apostrophes and hyphens as one word', () => {
        expect(wordsOf("Lisbon's well-known tram doesn't stop here.")).toEqual([
            "Lisbon's",
            'well-known',
            'tram',
            "doesn't",
            'stop',
            'here',
        ]);
    });

    it('keeps accents and the letter ñ inside Spanish words', () => {
        expect(wordsOf('El año pasado horneé pan en Logroño.')).toEqual([
            'El',
            'año',
            'pasado',
            'horneé',
            'pan',
            'en',
            'Logroño',
        ]);
    });

    it('keeps decimals as one word', () => {
        expect(
            wordsOf('The ticket costs 3.50 euros, or 3,10 with a card.'),
        ).toHaveLength(10);
    });

    it('ignores punctuation and Spanish question marks', () => {
        expect(wordsOf('¿Cuánto tarda? ¡Unas 24 horas!')).toEqual([
            'Cuánto',
            'tarda',
            'Unas',
            '24',
            'horas',
        ]);
    });
});

describe('normalize', () => {
    it('removes accents, lowers case and straightens quotes', () => {
        expect(normalize('Pan de MASA Madre ’Ñandú’ Graça')).toBe(
            "pan de masa madre 'nandu' graca",
        );
    });
});

describe('htmlToText', () => {
    it('strips tags, decodes entities and keeps block breaks as lines', () => {
        expect(
            htmlToText(
                '<p>Fish &amp; chips at <strong>Time&nbsp;Out</strong> Market</p><p>Open &#8220;daily&#8221;</p>',
            ),
        ).toBe('Fish & chips at Time Out Market\nOpen “daily”');
    });

    it('returns an empty string for empty HTML', () => {
        expect(htmlToText('<p></p>')).toBe('');
    });
});

describe('splitSentences', () => {
    it('splits English sentences on dots, question and exclamation marks', () => {
        expect(
            splitSentences(
                'The tram is full. Should we walk? Yes, let us walk!',
            ),
        ).toEqual([
            'The tram is full.',
            'Should we walk?',
            'Yes, let us walk!',
        ]);
    });

    it('does not split after common English abbreviations', () => {
        expect(
            splitSentences(
                'Dr. Silva met Mr. Costa at the stop. They talked about old trams, e.g. the ones from 1930.',
            ),
        ).toHaveLength(2);
    });

    it('does not split decimals or initials', () => {
        expect(
            splitSentences(
                'The ride takes 3.5 hours with J. R. Tolkien on the audio guide. It is long.',
            ),
        ).toEqual([
            'The ride takes 3.5 hours with J. R. Tolkien on the audio guide.',
            'It is long.',
        ]);
    });

    it('splits Spanish sentences with opening marks and abbreviations', () => {
        expect(
            splitSentences(
                '¿Cuánto tiempo hace falta? Unas 24 horas. ¡No lo cortes caliente! La Sra. Gómez lo sabe bien.',
            ),
        ).toEqual([
            '¿Cuánto tiempo hace falta?',
            'Unas 24 horas.',
            '¡No lo cortes caliente!',
            'La Sra. Gómez lo sabe bien.',
        ]);
    });

    it('treats new lines as sentence ends and keeps text without a final dot', () => {
        expect(
            splitSentences(
                'Where to catch the tram\nMost people start at Martim Moniz',
            ),
        ).toEqual([
            'Where to catch the tram',
            'Most people start at Martim Moniz',
        ]);
    });

    it('handles closing quotes and ellipses', () => {
        expect(
            splitSentences(
                'She said "hold on." Then the tram moved… We laughed.',
            ),
        ).toHaveLength(3);
    });
});

describe('languageOf', () => {
    it.each([
        ['en', 'en'],
        ['en_US', 'en'],
        ['EN-gb', 'en'],
        ['es', 'es'],
        ['es-ES', 'es'],
        ['es_MX', 'es'],
        ['pt_PT', 'other'],
        ['', 'other'],
    ])('maps %s to %s', (locale, language) => {
        expect(languageOf(locale)).toBe(language);
    });
});
