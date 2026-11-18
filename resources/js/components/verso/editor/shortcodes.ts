import type { Block, PluginBlockDefinition } from '@/types';
import { newBlockId } from './block-types';

// One shortcode read from text, like [form slug="contact"].
export type ParsedShortcode = {
    name: string;
    attrs: Record<string, string | true>;
    raw: string;
};

export type ShortcodePart =
    | { kind: 'text'; text: string }
    | { kind: 'shortcode'; shortcode: ParsedShortcode };

const NAME_START = /[a-z]/;
const NAME_CHAR = /[a-z0-9_-]/;
const ATTR_START = /[A-Za-z]/;
const ATTR_CHAR = /[A-Za-z0-9_-]/;
const SPACE = /\s/;

// Reads a name at `start`. Returns the name and the index after it, or null.
function readName(
    text: string,
    start: number,
    first: RegExp,
    rest: RegExp,
): [string, number] | null {
    if (!first.test(text[start] ?? '')) {
        return null;
    }

    let end = start + 1;

    while (end < text.length && rest.test(text[end])) {
        end++;
    }

    return [text.slice(start, end), end];
}

// Reads one tag that starts with "[" at `open`. Same rules as the server tokenizer, without enclosing tags.
export function readShortcode(
    text: string,
    open: number,
): { shortcode: ParsedShortcode; end: number } | null {
    const name = readName(text, open + 1, NAME_START, NAME_CHAR);

    if (name === null) {
        return null;
    }

    const attrs: Record<string, string | true> = {};
    let position = name[1];

    while (position < text.length) {
        while (SPACE.test(text[position] ?? '')) {
            position++;
        }

        const char = text[position];

        if (char === ']' || (char === '/' && text[position + 1] === ']')) {
            const end = position + (char === ']' ? 1 : 2);

            return {
                shortcode: { name: name[0], attrs, raw: text.slice(open, end) },
                end,
            };
        }

        const attr = readName(text, position, ATTR_START, ATTR_CHAR);

        if (attr === null) {
            return null;
        }

        position = attr[1];

        if (text[position] !== '=') {
            attrs[attr[0]] = true;
            continue;
        }

        const quote = text[position + 1];

        if (quote === '"' || quote === "'") {
            const close = text.indexOf(quote, position + 2);

            if (close === -1) {
                return null;
            }

            attrs[attr[0]] = text.slice(position + 2, close);
            position = close + 1;
            continue;
        }

        let end = position + 1;

        while (end < text.length && !/[\s\][]/.test(text[end])) {
            end++;
        }

        if (end === position + 1) {
            return null;
        }

        attrs[attr[0]] = text.slice(position + 1, end);
        position = end;
    }

    return null;
}

// Splits plain text into text and shortcodes. [[name]] stays text.
export function splitShortcodes(text: string): ShortcodePart[] {
    const parts: ShortcodePart[] = [];
    let buffer = '';
    let position = 0;

    while (position < text.length) {
        const open = text.indexOf('[', position);

        if (open === -1) {
            buffer += text.slice(position);
            break;
        }

        buffer += text.slice(position, open);

        if (text[open + 1] === '[') {
            const inner = readShortcode(text, open + 1);

            if (inner && text[inner.end] === ']') {
                buffer += text.slice(open, inner.end + 1);
                position = inner.end + 1;
                continue;
            }
        }

        const found = readShortcode(text, open);

        if (found === null) {
            buffer += '[';
            position = open + 1;
            continue;
        }

        if (buffer !== '') {
            parts.push({ kind: 'text', text: buffer });
            buffer = '';
        }

        parts.push({ kind: 'shortcode', shortcode: found.shortcode });
        position = found.end;
    }

    if (buffer !== '') {
        parts.push({ kind: 'text', text: buffer });
    }

    return parts;
}

// The plugin blocks that can be written as shortcodes.
export function shortcodeDefinitions(
    definitions: PluginBlockDefinition[],
): PluginBlockDefinition[] {
    return definitions.filter(
        (definition) => definition.shortcode === true && !definition.children,
    );
}

// The only shortcode of a pasted text, when it holds nothing else.
export function singleShortcode(text: string): ParsedShortcode | null {
    const parts = splitShortcodes(text.trim());

    return parts.length === 1 && parts[0].kind === 'shortcode'
        ? parts[0].shortcode
        : null;
}

// Shortcode attrs as block attrs: aliases renamed, flags true, toggle words turned into booleans.
export function shortcodeToAttrs(
    shortcode: ParsedShortcode,
    definition: PluginBlockDefinition,
): Record<string, unknown> | null {
    const aliases = definition.aliases ?? {};
    const attrs: Record<string, unknown> = {};

    for (const field of definition.fields) {
        if (field.default !== null && field.default !== undefined) {
            attrs[field.name] = field.default;
        }
    }

    for (const [key, value] of Object.entries(shortcode.attrs)) {
        const name = aliases[key] ?? key;
        const field = definition.fields.find((item) => item.name === name);

        if (!field) {
            return null;
        }

        attrs[name] =
            field.type === 'toggle'
                ? value === true || ['1', 'true', 'yes', 'on'].includes(value)
                : value === true
                  ? ''
                  : value;
    }

    return attrs;
}

// A block built from a shortcode, or null when the name or an attr is unknown.
export function shortcodeToBlock(
    shortcode: ParsedShortcode,
    definitions: PluginBlockDefinition[],
): Block | null {
    const definition = shortcodeDefinitions(definitions).find(
        (item) => item.name === shortcode.name,
    );
    const attrs = definition ? shortcodeToAttrs(shortcode, definition) : null;

    return attrs === null
        ? null
        : {
              id: newBlockId(),
              type: shortcode.name,
              attrs,
              content: '',
              children: [],
          };
}

// Writes block attrs back as a shortcode. Attr names keep the alias the author used.
export function attrsToShortcode(
    name: string,
    attrs: Record<string, unknown>,
    definition: PluginBlockDefinition | undefined,
    previous: ParsedShortcode | null = null,
): string {
    const aliases = definition?.aliases ?? {};
    const usedAlias = (field: string): string =>
        Object.keys(previous?.attrs ?? {}).find(
            (key) => aliases[key] === field,
        ) ?? field;
    const parts: string[] = [name];

    for (const [key, value] of Object.entries(attrs)) {
        const field = definition?.fields.find((item) => item.name === key);
        const keep =
            previous !== null &&
            Object.keys(previous.attrs).some(
                (used) => used === key || aliases[used] === key,
            );

        if (
            value === null ||
            value === undefined ||
            value === '' ||
            (!keep && field !== undefined && value === field.default)
        ) {
            continue;
        }

        if (typeof value === 'object') {
            continue;
        }

        const text =
            typeof value === 'boolean'
                ? value
                    ? 'true'
                    : 'false'
                : String(value as string | number);
        const quoted = text.includes('"') ? `'${text}'` : `"${text}"`;
        parts.push(`${usedAlias(key)}=${quoted}`);
    }

    return `[${parts.join(' ')}]`;
}

const HTML_ESCAPES: Record<string, string> = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
};

function escapeHtml(text: string): string {
    return text.replace(/[&<>"]/g, (char) => HTML_ESCAPES[char]);
}

function decodeHtml(text: string): string {
    return text
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&quot;/g, '"')
        .replace(/&#39;/g, "'")
        .replace(/&nbsp;/g, ' ')
        .replace(/&amp;/g, '&');
}

// Stored HTML to editor HTML: shortcodes of known names become chip spans. Text inside tags is left alone.
export function wrapShortcodeChips(html: string, names: string[]): string {
    if (names.length === 0 || !html.includes('[')) {
        return html;
    }

    return html
        .split(/(<[^>]*>)/)
        .map((piece) => {
            if (piece.startsWith('<')) {
                return piece;
            }

            return splitShortcodes(decodeHtml(piece))
                .map((part) =>
                    part.kind === 'shortcode' &&
                    names.includes(part.shortcode.name)
                        ? `<span data-shortcode="">${escapeHtml(part.shortcode.raw)}</span>`
                        : escapeHtml(
                              part.kind === 'text'
                                  ? part.text
                                  : part.shortcode.raw,
                          ),
                )
                .join('');
        })
        .join('');
}

// Editor HTML to stored HTML: chips go back to plain shortcode text.
export function unwrapShortcodeChips(html: string): string {
    return html.replace(
        /<span data-shortcode="">([\s\S]*?)<\/span>/g,
        (_, inner: string) => inner,
    );
}
