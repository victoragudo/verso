import type { AnalysisConfig, Rating } from './types';

export const DEFAULT_ANALYSIS_CONFIG: AnalysisConfig = {
    titleMin: 30,
    titleMax: 60,
    descriptionMin: 120,
    descriptionMax: 160,
    minWords: 300,
    keyphraseMaxWords: 4,
    keyphraseDensityMin: 0.5,
    keyphraseDensityMax: 3,
    slugMaxWords: 6,
    sentenceMaxWords: 20,
    longSentencesMaxPercent: 25,
    paragraphMaxWords: 150,
    subheadingMaxWords: 300,
    passiveMaxPercent: 10,
    transitionMinPercent: 30,
    goodScore: 70,
    okScore: 40,
};

// Turns a score from 0 to 100 into a rating.
export function ratingOf(
    score: number,
    config: Pick<AnalysisConfig, 'goodScore' | 'okScore'>,
): Rating {
    if (score >= config.goodScore) {
        return 'good';
    }

    return score >= config.okScore ? 'ok' : 'poor';
}
