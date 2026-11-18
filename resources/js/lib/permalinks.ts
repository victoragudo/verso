// Helpers for the permalink settings page. They mirror the PHP PermalinkPattern rules,
// so the live preview and the first checks match what the server will save.

import { t } from '@/lib/i18n';

export type PermalinkSample = Record<string, string>;

const SLUG_TOKEN = '{slug}';
const ID_TOKEN = '{id}';
const TOKEN = /\{([^}]*)\}/g;

// Keeps one leading slash, no trailing slash and no empty parts.
export function normalizePattern(pattern: string): string {
    const parts = pattern
        .trim()
        .split('/')
        .map((part) => part.trim())
        .filter((part) => part !== '');

    return `/${parts.join('/')}`;
}

export function patternTokens(pattern: string): string[] {
    return Array.from(pattern.matchAll(TOKEN), (match) => match[1]);
}

// The path with every token replaced, like "/2026/03/riding-tram-28".
export function buildPermalink(
    pattern: string,
    sample: PermalinkSample,
    trailingSlash: boolean,
): string {
    const path = normalizePattern(pattern).replace(
        TOKEN,
        (_, token: string) => sample[token] ?? `{${token}}`,
    );

    return withTrailingSlash(path, trailingSlash);
}

// "/blog" or "/blog/", never a slash after a file name like "sitemap.xml".
export function withTrailingSlash(path: string, on: boolean): string {
    const last = path.split('/').pop() ?? '';

    if (!on || path === '/' || last.includes('.')) {
        return path;
    }

    return `${path}/`;
}

// The first problem the browser can see, or null. The server checks clashes too.
export function patternProblem(
    pattern: string,
    hierarchical: boolean,
    tokens: string[],
): string | null {
    const normalized = normalizePattern(pattern);
    const used = patternTokens(normalized);

    if (!/^\/[a-z0-9_./{}-]*$/.test(normalized)) {
        return t(
            'Use lowercase letters, numbers, dashes, dots, slashes and tokens only.',
        );
    }

    const unknown = used.find((token) => !tokens.includes(token));

    if (unknown !== undefined) {
        return t('Unknown token {token}.', { token: `{${unknown}}` });
    }

    if (new Set(used).size !== used.length) {
        return t('Use each token only once.');
    }

    if (
        normalized
            .split('/')
            .some((part) => (part.match(/\{/g) ?? []).length > 1)
    ) {
        return t('Put a slash between two tokens.');
    }

    if (hierarchical && !used.includes('slug')) {
        return t('Nested entries need the {token} token.', {
            token: SLUG_TOKEN,
        });
    }

    if (!used.includes('slug') && !used.includes('id')) {
        return t('The structure needs {slug} or {id}.', {
            slug: SLUG_TOKEN,
            id: ID_TOKEN,
        });
    }

    return null;
}

// The preset that matches a structure, or "custom".
export function presetFor(
    pattern: string,
    presets: Record<string, string>,
): string {
    const normalized = normalizePattern(pattern);

    return (
        Object.keys(presets).find((key) => presets[key] === normalized) ??
        'custom'
    );
}
