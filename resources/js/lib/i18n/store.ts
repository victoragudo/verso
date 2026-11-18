// The admin language and its strings. Keys are the English text, so a missing
// translation shows English. Plugin and theme keys start with "<slug>::".
import { formatMessage } from './format';
import type { MessageValues } from './format';

export type Messages = Record<string, string>;
export type TextDirection = 'ltr' | 'rtl';

type State = {
    locale: string;
    dir: TextDirection;
    messages: Messages;
};

const NAMESPACE = /^[a-z0-9][a-z0-9-]*::/;

let state: State = { locale: 'en', dir: 'ltr', messages: {} };
const listeners = new Set<() => void>();
const bundles = new Map<string, Promise<Messages>>();

// The text of a key without the plugin or theme prefix.
export function sourceText(key: string): string {
    return key.replace(NAMESPACE, '');
}

// Translates a key and fills its values: t('Saved {title}', { title }).
export function t(key: string, values?: MessageValues): string {
    const message = state.messages[key] || sourceText(key);

    return formatMessage(message, values, state.locale);
}

// Marks a string for translation where t() cannot run yet, like module level
// constants. Pass the result to t() when rendering.
export function msg<T extends string>(key: T): T {
    return key;
}

export function hasTranslation(key: string): boolean {
    return key in state.messages;
}

export function currentLocale(): string {
    return state.locale;
}

export function currentDir(): TextDirection {
    return state.dir;
}

export function setMessages(
    locale: string,
    messages: Messages,
    dir: TextDirection = 'ltr',
): void {
    state = { locale, dir, messages };
    listeners.forEach((listener) => listener());
}

export function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

export function snapshot(): State {
    return state;
}

// Fetches a bundle once per URL. A failed request falls back to English text.
export function fetchBundle(url: string | null): Promise<Messages> {
    if (!url) {
        return Promise.resolve({});
    }

    let bundle = bundles.get(url);

    if (!bundle) {
        bundle = fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
            .then((response) => (response.ok ? response.json() : {}))
            .then((data: unknown) =>
                data && typeof data === 'object' ? (data as Messages) : {},
            )
            .catch(() => {
                bundles.delete(url);

                return {};
            });
        bundles.set(url, bundle);
    }

    return bundle;
}

// Loads a language and makes it current. The html element gets lang and dir.
export async function loadLocale(
    locale: string,
    url: string | null,
    dir: TextDirection = 'ltr',
): Promise<void> {
    const messages = await fetchBundle(url);
    setMessages(locale, messages, dir);

    if (typeof document !== 'undefined') {
        document.documentElement.lang = locale;
        document.documentElement.dir = dir;
    }
}
