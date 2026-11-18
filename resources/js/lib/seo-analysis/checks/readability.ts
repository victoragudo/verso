import { msg, t } from '@/lib/i18n';
import { languageData } from '../languages';
import {
    EN_IRREGULAR_PARTICIPLES,
    EN_NOT_PARTICIPLES,
    EN_PASSIVE_FILLERS,
    EN_TO_BE,
} from '../languages/en';
import { tokensOf, wordsOf } from '../text';
import type {
    AnalysisCheck,
    AnalysisContext,
    AnalysisLanguage,
} from '../types';
import { good, ok, percent, problem } from './helpers';

// How far over a limit is still only a small problem.
const LONG_SENTENCES_EXTRA_PERCENT = 5;
const LONG_PARAGRAPH_FACTOR = 4 / 3;
const LONG_SECTION_FACTOR = 7 / 6;
const PASSIVE_OK_FACTOR = 1.5;
const TRANSITION_OK_FACTOR = 2 / 3;
const MIN_SENTENCES_FOR_TRANSITIONS = 5;
const SAME_START_RUN = 3;
const MAX_PASSIVE_FILLERS = 2;
// Below this many words the readability checks have too little to judge.
const MIN_WORDS_FOR_READABILITY = 50;

const TO_BE = new Set([...EN_TO_BE, 'isn', 'aren', 'wasn', 'weren']);
const PASSIVE_FILLERS = new Set([...EN_PASSIVE_FILLERS, 't']);
const IRREGULAR_PARTICIPLES = new Set(EN_IRREGULAR_PARTICIPLES);
const NOT_PARTICIPLES = new Set(EN_NOT_PARTICIPLES);

function isParticiple(word: string): boolean {
    if (IRREGULAR_PARTICIPLES.has(word)) {
        return true;
    }

    return word.length > 3 && word.endsWith('ed') && !NOT_PARTICIPLES.has(word);
}

function isFiller(word: string): boolean {
    return (
        PASSIVE_FILLERS.has(word) || (word.length > 4 && word.endsWith('ly'))
    );
}

// True when an English sentence looks passive, like "The tram was built in 1914".
export function isPassiveSentence(sentence: string): boolean {
    const tokens = tokensOf(sentence);

    for (let i = 0; i < tokens.length; i++) {
        if (!TO_BE.has(tokens[i])) {
            continue;
        }

        for (
            let j = i + 1;
            j < tokens.length && j <= i + 1 + MAX_PASSIVE_FILLERS;
            j++
        ) {
            if (isParticiple(tokens[j])) {
                return true;
            }

            if (!isFiller(tokens[j])) {
                break;
            }
        }
    }

    return false;
}

// True when a sentence uses a transition word like "however" or "sin embargo".
export function hasTransitionWord(
    sentence: string,
    language: AnalysisLanguage,
): boolean {
    const data = languageData(language);
    const tokens = tokensOf(sentence);

    if (tokens.some((token) => data.transitionWords.has(token))) {
        return true;
    }

    const joined = ` ${tokens.join(' ')} `;

    return data.transitionPhrases.some((phrase) =>
        joined.includes(` ${phrase} `),
    );
}

function tooShort(context: AnalysisContext): boolean {
    return context.content.words.length < MIN_WORDS_FOR_READABILITY;
}

export const READABILITY_CHECKS: AnalysisCheck[] = [
    {
        id: 'sentence-length',
        group: 'readability',
        label: msg('Sentence length'),
        run: (context) => {
            if (tooShort(context)) {
                return null;
            }

            const { content, config } = context;

            const sentences = content.sentences;

            if (sentences.length === 0) {
                return null;
            }

            const long = sentences.filter(
                (sentence) =>
                    wordsOf(sentence).length > config.sentenceMaxWords,
            ).length;
            const share = percent(long, sentences.length);
            const limit = config.longSentencesMaxPercent;

            if (share <= limit) {
                return good(
                    t('Your sentences are short and easy to read. Great.'),
                );
            }

            const message = t(
                '{share, number}% of your sentences have more than {max, plural, one {# word} other {# words}}. Try to keep it under {limit, number}%.',
                { share, max: config.sentenceMaxWords, limit },
            );

            return share <= limit + LONG_SENTENCES_EXTRA_PERCENT
                ? ok(message)
                : problem(message);
        },
    },
    {
        id: 'paragraph-length',
        group: 'readability',
        label: msg('Paragraph length'),
        run: (context) => {
            if (tooShort(context)) {
                return null;
            }

            const { content, config } = context;

            if (content.paragraphs.length === 0) {
                return null;
            }

            const lengths = content.paragraphs.map(
                (paragraph) => wordsOf(paragraph).length,
            );
            const tooLong = lengths.filter(
                (length) => length > config.paragraphMaxWords,
            ).length;

            if (tooLong === 0) {
                return good(t('Your paragraphs have a good length.'));
            }

            const message = t(
                '{count, plural, one {# paragraph is} other {# paragraphs are}} longer than {max, plural, one {# word} other {# words}}. Split long paragraphs.',
                { count: tooLong, max: config.paragraphMaxWords },
            );
            const longest = Math.max(...lengths);

            return longest <= config.paragraphMaxWords * LONG_PARAGRAPH_FACTOR
                ? ok(message)
                : problem(message);
        },
    },
    {
        id: 'subheading-distribution',
        group: 'readability',
        label: msg('Subheadings'),
        run: ({ content, config }) => {
            const limit = config.subheadingMaxWords;

            if (content.words.length <= limit) {
                return null;
            }

            if (content.headings.length === 0) {
                return problem(
                    t('Add subheadings to split your text into parts.'),
                );
            }

            const longest = Math.max(...content.wordsBetweenHeadings);

            if (longest <= limit) {
                return good(t('Your text is split well with subheadings.'));
            }

            const message = t(
                'One part of your text has {count, plural, one {# word} other {# words}} without a subheading. Add a subheading at least every {limit, plural, one {# word} other {# words}}.',
                { count: longest, limit },
            );

            return longest <= limit * LONG_SECTION_FACTOR
                ? ok(message)
                : problem(message);
        },
    },
    {
        id: 'heading-structure',
        group: 'readability',
        label: msg('Heading order'),
        run: ({ content }) => {
            if (content.headings.length === 0) {
                return null;
            }

            let previous = 1;

            for (const heading of content.headings) {
                if (heading.level > previous + 1) {
                    return ok(
                        t(
                            'An H{level} comes after an H{previous}. Do not skip heading levels.',
                            { level: heading.level, previous },
                        ),
                    );
                }

                previous = heading.level;
            }

            return good(t('Your headings are in a good order.'));
        },
    },
    {
        id: 'passive-voice',
        group: 'readability',
        label: msg('Passive voice'),
        languages: ['en'],
        run: (context) => {
            if (tooShort(context)) {
                return null;
            }

            const { content, config } = context;

            const sentences = content.sentences;

            if (sentences.length === 0) {
                return null;
            }

            const passive = sentences.filter(isPassiveSentence).length;
            const share = percent(passive, sentences.length);
            const limit = config.passiveMaxPercent;

            if (share <= limit) {
                return good(t('You use the passive voice very little. Great.'));
            }

            const message = t(
                '{share, number}% of your sentences use the passive voice. Try to keep it under {limit, number}%.',
                { share, limit },
            );

            return share <= limit * PASSIVE_OK_FACTOR
                ? ok(message)
                : problem(message);
        },
    },
    {
        id: 'transition-words',
        group: 'readability',
        label: msg('Transition words'),
        languages: ['en', 'es'],
        run: ({ content, config, language }) => {
            const sentences = content.sentences;

            if (sentences.length < MIN_SENTENCES_FOR_TRANSITIONS) {
                return null;
            }

            const linked = sentences.filter((sentence) =>
                hasTransitionWord(sentence, language),
            ).length;
            const share = percent(linked, sentences.length);
            const target = config.transitionMinPercent;

            if (share >= target) {
                return good(
                    t(
                        '{share, number}% of your sentences use transition words. Well done.',
                        { share },
                    ),
                );
            }

            const message = t(
                'Only {share, number}% of your sentences use transition words like "however" or "for example". Aim for {target, number}%.',
                { share, target },
            );

            return share >= target * TRANSITION_OK_FACTOR
                ? ok(message)
                : problem(message);
        },
    },
    {
        id: 'consecutive-sentences',
        group: 'readability',
        label: msg('Sentence starts'),
        run: (context) => {
            if (tooShort(context)) {
                return null;
            }

            const { content } = context;

            const starts = content.sentences.map(
                (sentence) => tokensOf(sentence)[0] ?? '',
            );

            if (starts.length < SAME_START_RUN) {
                return null;
            }

            let run = 1;

            for (let i = 1; i < starts.length; i++) {
                run =
                    starts[i] !== '' && starts[i] === starts[i - 1]
                        ? run + 1
                        : 1;

                if (run >= SAME_START_RUN) {
                    return ok(
                        t(
                            '{count, plural, one {# sentence} other {# sentences}} in a row start with "{word}". Mix up how your sentences start.',
                            { count: run, word: starts[i] },
                        ),
                    );
                }
            }

            return good(t('Your sentences start in different ways. Nice.'));
        },
    },
];
