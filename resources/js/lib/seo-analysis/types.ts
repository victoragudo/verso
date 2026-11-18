import type { Block } from '@/types';

export type AnalysisLanguage = 'en' | 'es' | 'other';
export type CheckStatus = 'good' | 'ok' | 'problem';
export type CheckGroup = 'seo' | 'readability';
export type Rating = 'good' | 'ok' | 'poor' | 'none';

// Everything the analysis looks at for one entry.
export type AnalysisInput = {
    title: string;
    seoTitle: string;
    description: string;
    slug: string;
    keyphrase: string;
    blocks: Block[];
    locale: string;
    siteHost: string;
    hasFeaturedImage: boolean;
};

// Limits used by the checks. Every value can be changed.
export type AnalysisConfig = {
    titleMin: number;
    titleMax: number;
    descriptionMin: number;
    descriptionMax: number;
    minWords: number;
    keyphraseMaxWords: number;
    keyphraseDensityMin: number;
    keyphraseDensityMax: number;
    slugMaxWords: number;
    sentenceMaxWords: number;
    longSentencesMaxPercent: number;
    paragraphMaxWords: number;
    subheadingMaxWords: number;
    passiveMaxPercent: number;
    transitionMinPercent: number;
    goodScore: number;
    okScore: number;
};

// The result of one check, as shown to the user.
export type CheckResult = {
    id: string;
    group: CheckGroup;
    status: CheckStatus;
    label: string;
    message: string;
};

export type ParsedHeading = { level: number; text: string };
export type ParsedLink = {
    href: string;
    text: string;
    internal: boolean;
    nofollow: boolean;
};
export type ParsedImage = { src: string; alt: string };

// The readable parts of the blocks.
export type ParsedContent = {
    text: string;
    words: string[];
    sentences: string[];
    paragraphs: string[];
    firstParagraph: string;
    headings: ParsedHeading[];
    links: ParsedLink[];
    images: ParsedImage[];
    // Word counts of the text before the first heading and after each heading.
    wordsBetweenHeadings: number[];
};

// Finds the focus keyphrase in a text.
export type KeyphraseMatcher = {
    isEmpty: boolean;
    words: string[];
    matches: (text: string) => boolean;
    count: (text: string) => number;
};

export type AnalysisContext = {
    input: AnalysisInput;
    config: AnalysisConfig;
    language: AnalysisLanguage;
    content: ParsedContent;
    keyphrase: KeyphraseMatcher;
};

export type CheckOutcome = { status: CheckStatus; message: string };

// One check. Plugins can add their own with registerAnalysisCheck().
export type AnalysisCheck = {
    id: string;
    group: CheckGroup;
    label: string;
    weight?: number;
    needsKeyphrase?: boolean;
    languages?: AnalysisLanguage[];
    run: (context: AnalysisContext) => CheckOutcome | null;
};

export type AnalysisResult = {
    checks: CheckResult[];
    seoScore: number;
    readabilityScore: number;
    score: number;
    rating: Rating;
    language: AnalysisLanguage;
    wordCount: number;
};
