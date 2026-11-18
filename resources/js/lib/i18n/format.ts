// A small ICU message formatter: {name}, {n, number}, {n, plural, ...},
// {n, selectordinal, ...} and {x, select, ...}, with # inside plurals.
// Plural forms come from Intl.PluralRules, numbers from Intl.NumberFormat.

export type MessageValue = string | number | boolean | null | undefined | Date;
export type MessageValues = Record<string, MessageValue>;

type Node =
    | { kind: 'text'; value: string }
    | { kind: 'arg'; name: string; style?: string }
    | { kind: 'pound' }
    | {
          kind: 'plural' | 'selectordinal';
          name: string;
          offset: number;
          options: Record<string, Node[]>;
      }
    | { kind: 'select'; name: string; options: Record<string, Node[]> };

const cache = new Map<string, Node[]>();
const pluralRules = new Map<string, Intl.PluralRules>();
const numberFormats = new Map<string, Intl.NumberFormat>();

class Parser {
    private index = 0;

    constructor(private readonly source: string) {}

    parse(): Node[] {
        const nodes = this.nodes(false, 0);

        return nodes;
    }

    // Reads text and arguments until the end, or until a closing brace at depth > 0.
    private nodes(inPlural: boolean, depth: number): Node[] {
        const nodes: Node[] = [];
        let text = '';

        const flush = () => {
            if (text !== '') {
                nodes.push({ kind: 'text', value: text });
                text = '';
            }
        };

        while (this.index < this.source.length) {
            const char = this.source[this.index];

            if (char === "'") {
                text += this.quoted(inPlural);
                continue;
            }

            if (char === '{') {
                flush();
                nodes.push(this.argument(depth));
                continue;
            }

            if (char === '}') {
                if (depth > 0) {
                    break;
                }

                text += char;
                this.index++;
                continue;
            }

            if (char === '#' && inPlural) {
                flush();
                nodes.push({ kind: 'pound' });
                this.index++;
                continue;
            }

            text += char;
            this.index++;
        }

        flush();

        return nodes;
    }

    // ICU apostrophe rules: '' is one apostrophe, '{' starts quoted text.
    private quoted(inPlural: boolean): string {
        const next = this.source[this.index + 1];

        if (next === "'") {
            this.index += 2;

            return "'";
        }

        if (next === '{' || next === '}' || (inPlural && next === '#')) {
            const end = this.source.indexOf("'", this.index + 1);
            const stop = end === -1 ? this.source.length : end;
            const value = this.source.slice(this.index + 1, stop);
            this.index = stop + 1;

            return value;
        }

        this.index++;

        return "'";
    }

    private argument(depth: number): Node {
        const start = this.index;
        this.index++;
        const name = this.word();
        this.space();

        if (this.source[this.index] === '}') {
            this.index++;

            return { kind: 'arg', name };
        }

        if (this.source[this.index] !== ',') {
            return this.broken(start);
        }

        this.index++;
        this.space();
        const type = this.word();
        this.space();

        if (this.source[this.index] === '}') {
            this.index++;

            return { kind: 'arg', name, style: type };
        }

        if (this.source[this.index] !== ',') {
            return this.broken(start);
        }

        this.index++;

        if (
            type === 'plural' ||
            type === 'selectordinal' ||
            type === 'select'
        ) {
            return this.options(name, type, depth);
        }

        const styleEnd = this.source.indexOf('}', this.index);

        if (styleEnd === -1) {
            return this.broken(start);
        }

        const style = this.source.slice(this.index, styleEnd).trim();
        this.index = styleEnd + 1;

        return { kind: 'arg', name, style: `${type}:${style}` };
    }

    private options(
        name: string,
        type: 'plural' | 'selectordinal' | 'select',
        depth: number,
    ): Node {
        const options: Record<string, Node[]> = {};
        let offset = 0;

        for (;;) {
            this.space();

            if (this.source[this.index] === '}') {
                this.index++;
                break;
            }

            if (this.index >= this.source.length) {
                break;
            }

            const selector = this.word();

            if (selector === '') {
                this.index++;
                continue;
            }

            if (selector.startsWith('offset:')) {
                offset = Number(selector.slice(7)) || 0;
                continue;
            }

            this.space();

            if (this.source[this.index] !== '{') {
                break;
            }

            this.index++;
            options[selector] = this.nodes(type !== 'select', depth + 1);
            this.index++;
        }

        if (type === 'select') {
            return { kind: 'select', name, options };
        }

        return { kind: type, name, offset, options };
    }

    // Text that does not parse is kept as it is, so a broken translation still shows.
    private broken(start: number): Node {
        const end = this.source.indexOf('}', start);
        const stop = end === -1 ? this.source.length : end + 1;
        this.index = stop;

        return { kind: 'text', value: this.source.slice(start, stop) };
    }

    private word(): string {
        const match = /^[^\s{},]+/.exec(this.source.slice(this.index));

        if (!match) {
            return '';
        }

        this.index += match[0].length;

        return match[0];
    }

    private space(): void {
        while (/\s/.test(this.source[this.index] ?? '')) {
            this.index++;
        }
    }
}

export function parseMessage(message: string): Node[] {
    let nodes = cache.get(message);

    if (!nodes) {
        nodes = new Parser(message).parse();
        cache.set(message, nodes);
    }

    return nodes;
}

function rules(locale: string, type: Intl.PluralRuleType): Intl.PluralRules {
    const key = `${locale}|${type}`;
    let rule = pluralRules.get(key);

    if (!rule) {
        rule = new Intl.PluralRules(locale, { type });
        pluralRules.set(key, rule);
    }

    return rule;
}

function numberFormat(
    locale: string,
    style: string | undefined,
): Intl.NumberFormat {
    const key = `${locale}|${style ?? ''}`;
    let format = numberFormats.get(key);

    if (!format) {
        const options: Intl.NumberFormatOptions =
            style === 'number:percent'
                ? { style: 'percent' }
                : style === 'number:integer'
                  ? { maximumFractionDigits: 0 }
                  : {};
        format = new Intl.NumberFormat(locale, options);
        numberFormats.set(key, format);
    }

    return format;
}

function display(value: MessageValue, locale: string, style?: string): string {
    if (value === null || value === undefined) {
        return '';
    }

    if (typeof value === 'number') {
        return numberFormat(locale, style).format(value);
    }

    if (value instanceof Date) {
        return new Intl.DateTimeFormat(locale, {
            dateStyle: 'medium',
        }).format(value);
    }

    return String(value);
}

function render(
    nodes: Node[],
    values: MessageValues,
    locale: string,
    pound: number | null,
): string {
    let output = '';

    for (const node of nodes) {
        switch (node.kind) {
            case 'text':
                output += node.value;
                break;
            case 'pound':
                output += pound === null ? '#' : display(pound, locale);
                break;
            case 'arg':
                output +=
                    node.name in values
                        ? display(values[node.name], locale, node.style)
                        : `{${node.name}}`;
                break;
            case 'select': {
                const value = String(values[node.name] ?? 'other');
                const branch = node.options[value] ?? node.options.other ?? [];
                output += render(branch, values, locale, pound);
                break;
            }
            default: {
                const raw = Number(values[node.name] ?? 0);
                const number = raw - node.offset;
                const exact = node.options[`=${raw}`];
                const category = rules(
                    locale,
                    node.kind === 'plural' ? 'cardinal' : 'ordinal',
                ).select(number);
                const branch =
                    exact ?? node.options[category] ?? node.options.other ?? [];
                output += render(branch, values, locale, number);
            }
        }
    }

    return output;
}

// Formats an ICU message with values in a locale.
export function formatMessage(
    message: string,
    values: MessageValues = {},
    locale = 'en',
): string {
    if (!message.includes('{') && !message.includes("'")) {
        return message;
    }

    return render(parseMessage(message), values, locale, null);
}

// The argument names a message uses, for checks that translations keep them.
export function messageArguments(message: string): string[] {
    const names = new Set<string>();

    const walk = (nodes: Node[]) => {
        for (const node of nodes) {
            if (node.kind === 'arg') {
                names.add(node.name);
            } else if (node.kind !== 'text' && node.kind !== 'pound') {
                names.add(node.name);
                Object.values(node.options).forEach(walk);
            }
        }
    };

    walk(new Parser(message).parse());

    return [...names].sort();
}
