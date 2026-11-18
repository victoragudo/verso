import { normalize } from '@/components/verso/shell/command-menu';
import { t } from '@/lib/i18n';
import { help } from '@/routes/admin/easy';

// Help panel topics: order them for the current page, search them and load them once.

export type HelpTopic = {
    key: string;
    title: string;
    summary: string;
    steps: string[];
    illustration: string;
    routes: string[];
    keywords: string[];
    video: string | null;
};

// Topics for the current page first (in their own order), then the rest.
export function orderTopics(
    topics: HelpTopic[],
    routeName: string | null | undefined,
): HelpTopic[] {
    if (!routeName) {
        return topics;
    }

    const here = topics.filter((topic) => topic.routes.includes(routeName));

    return [...here, ...topics.filter((topic) => !here.includes(topic))];
}

// True when the topic is meant for the current page.
export function isTopicForRoute(
    topic: HelpTopic,
    routeName: string | null | undefined,
): boolean {
    return Boolean(routeName) && topic.routes.includes(routeName as string);
}

// Topics that contain every word of the query in the title, summary, steps or keywords, in their order.
export function searchTopics(topics: HelpTopic[], query: string): HelpTopic[] {
    const words = normalize(query).split(/\s+/).filter(Boolean);

    if (words.length === 0) {
        return topics;
    }

    return topics.filter((topic) => {
        const text = normalize(
            [
                topic.title,
                topic.summary,
                ...topic.steps,
                ...topic.keywords,
            ].join(' '),
        );

        return words.every((word) => text.includes(word));
    });
}

// The sentence read to screen reader users after a search.
export function resultsMessage(count: number, query: string): string {
    if (normalize(query) === '') {
        return '';
    }

    if (count === 0) {
        return t('No help found for “{query}”.', { query: query.trim() });
    }

    return t('{count, plural, one {# topic found.} other {# topics found.}}', {
        count,
    });
}

export class HelpTopicsError extends Error {}

let cached: Promise<HelpTopic[]> | null = null;

// Loads the topics from the server once per page load. A failed load is tried again next time.
export function loadHelpTopics(): Promise<HelpTopic[]> {
    cached ??= fetch(help.url(), {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    })
        .then(async (response) => {
            if (!response.ok) {
                throw new HelpTopicsError(t('Help could not be loaded.'));
            }

            return ((await response.json()) as { topics: HelpTopic[] }).topics;
        })
        .catch((error: unknown) => {
            cached = null;

            throw error instanceof HelpTopicsError
                ? error
                : new HelpTopicsError(t('Help could not be loaded.'));
        });

    return cached;
}

// Forgets the loaded topics. Used by tests.
export function clearHelpTopics(): void {
    cached = null;
}
