import type { Block } from '@/types';

// What the server shares with the entry editor when the user may use the assistant.
export type AiAssistantProps = {
    ready: boolean;
    settingsUrl: string | null;
    endpoints: {
        outline: string;
        rewrite: string;
        excerpt: string;
        seo: string;
        altText: string;
        translate: string;
    };
    locales: { code: string; name: string }[];
};

// One row of the usage table: tokens of one user on one day.
export type AiUsageRow = {
    id: number;
    day: string;
    user: string;
    email: string | null;
    requests: number;
    input_tokens: number;
    output_tokens: number;
};

// Settings shown on the admin page.
export type AiSettingsInfo = {
    provider: string;
    hasKey: boolean;
    maskedKey: string | null;
    model: string;
    defaultModel: string;
    maxTokens: number;
    maxTokensCeiling: number;
    rateLimit: number;
    rateLimitMinutes: number;
};

// Results of each editor action.
export type OutlineResult = { blocks: Block[] };
export type RewriteResult = { text: string };
export type ExcerptResult = { excerpt: string };
export type SeoResult = { title: string; description: string };
export type AltTextResult = { alt: string };
export type TranslateResult = {
    title: string;
    blocks: Block[];
    locale: string;
};
