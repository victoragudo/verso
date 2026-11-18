import { Fragment } from 'react';
import type { ReactNode } from 'react';

type Renderers = Record<string, (children: ReactNode) => ReactNode>;

const TAG = /<([a-z][a-z0-9]*)>(.*?)<\/\1>/gs;

// Puts React elements into a translated text with tags:
// <Trans text={t('Read the <link>guide</link>.')} components={{ link: (c) => <a href="/guide">{c}</a> }} />
// Unknown tags are shown as plain text without the tag.
export function Trans({
    text,
    components,
}: {
    text: string;
    components: Renderers;
}) {
    const parts: ReactNode[] = [];
    let last = 0;

    for (const match of text.matchAll(TAG)) {
        const [whole, tag, inner] = match;
        const start = match.index ?? 0;

        if (start > last) {
            parts.push(text.slice(last, start));
        }

        const render = components[tag];
        parts.push(
            <Fragment key={start}>{render ? render(inner) : inner}</Fragment>,
        );
        last = start + whole.length;
    }

    if (last < text.length) {
        parts.push(text.slice(last));
    }

    return <>{parts}</>;
}
