import { languageData } from './languages';
import { splitSentences, tokensOf } from './text';
import type { AnalysisLanguage, KeyphraseMatcher } from './types';

const MIN_VARIANT_LENGTH = 2;

// Simple plural and singular forms of one word, so "trams" finds "tram" and "panes" finds "pan".
function variantsOf(word: string, language: AnalysisLanguage): Set<string> {
    const forms = [word, `${word}s`];

    if (word.endsWith('s') && !word.endsWith('ss')) {
        forms.push(word.slice(0, -1));
    }

    if (language !== 'other') {
        forms.push(`${word}es`);

        if (word.endsWith('es')) {
            forms.push(word.slice(0, -2));
        }
    }

    if (language === 'en') {
        if (word.endsWith('ies')) {
            forms.push(`${word.slice(0, -3)}y`);
        } else if (word.endsWith('y')) {
            forms.push(`${word.slice(0, -1)}ies`);
        }
    }

    if (language === 'es') {
        if (word.endsWith('ces')) {
            forms.push(`${word.slice(0, -3)}z`);
        } else if (word.endsWith('z')) {
            forms.push(`${word.slice(0, -1)}ces`);
        }
    }

    return new Set(forms.filter((form) => form.length >= MIN_VARIANT_LENGTH));
}

// The words of a keyphrase that matter: stop words like "the" or "de" are left out.
export function keyphraseWords(
    keyphrase: string,
    language: AnalysisLanguage,
): string[] {
    const tokens = [...new Set(tokensOf(keyphrase))];
    const stopWords = languageData(language).stopWords;
    const contentWords = tokens.filter((token) => !stopWords.has(token));

    return contentWords.length > 0 ? contentWords : tokens;
}

// Builds a matcher for the focus keyphrase. Accents, case, word order and plurals do not matter.
export function createKeyphraseMatcher(
    keyphrase: string,
    language: AnalysisLanguage,
): KeyphraseMatcher {
    const words = keyphraseWords(keyphrase, language);
    const variants = words.map((word) => variantsOf(word, language));
    const isEmpty = words.length === 0;

    const indexOf = (token: string): number =>
        variants.findIndex((forms) => forms.has(token));

    const matches = (text: string): boolean => {
        if (isEmpty || text === '') {
            return false;
        }

        const found = new Set<number>();

        for (const token of tokensOf(text)) {
            const index = indexOf(token);

            if (index >= 0) {
                found.add(index);

                if (found.size === words.length) {
                    return true;
                }
            }
        }

        return false;
    };

    const countInSentence = (sentence: string): number => {
        const counts = words.map(() => 0);

        for (const token of tokensOf(sentence)) {
            const index = indexOf(token);

            if (index >= 0) {
                counts[index]++;
            }
        }

        return Math.min(...counts);
    };

    const count = (text: string): number => {
        if (isEmpty || text === '') {
            return 0;
        }

        return splitSentences(text).reduce(
            (total, sentence) => total + countInSentence(sentence),
            0,
        );
    };

    return { isEmpty, words, matches, count };
}
