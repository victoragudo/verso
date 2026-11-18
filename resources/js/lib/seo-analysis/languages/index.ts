import type { AnalysisLanguage } from '../types';
import {
    EN_STOP_WORDS,
    EN_TRANSITION_PHRASES,
    EN_TRANSITION_WORDS,
} from './en';
import {
    ES_STOP_WORDS,
    ES_TRANSITION_PHRASES,
    ES_TRANSITION_WORDS,
} from './es';

// Word lists for one language.
export type LanguageData = {
    stopWords: ReadonlySet<string>;
    transitionWords: ReadonlySet<string>;
    transitionPhrases: readonly string[];
};

const LANGUAGES: Record<AnalysisLanguage, LanguageData> = {
    en: {
        stopWords: new Set(EN_STOP_WORDS),
        transitionWords: new Set(EN_TRANSITION_WORDS),
        transitionPhrases: EN_TRANSITION_PHRASES,
    },
    es: {
        stopWords: new Set(ES_STOP_WORDS),
        transitionWords: new Set(ES_TRANSITION_WORDS),
        transitionPhrases: ES_TRANSITION_PHRASES,
    },
    other: {
        stopWords: new Set(),
        transitionWords: new Set(),
        transitionPhrases: [],
    },
};

// The analysis language of a locale like 'en_US' or 'es-ES'.
export function languageOf(locale: string): AnalysisLanguage {
    const code = locale.trim().toLowerCase().split(/[-_]/)[0];

    return code === 'en' || code === 'es' ? code : 'other';
}

export function languageData(language: AnalysisLanguage): LanguageData {
    return LANGUAGES[language];
}
