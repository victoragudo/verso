// Small text helpers: clean HTML, count words and split sentences.

const WORD_PATTERN =
    /[\p{L}\p{N}]+(?:(?:['’-]|(?<=\p{N})[.,](?=\p{N}))[\p{L}\p{N}]+)*/gu;
const TOKEN_PATTERN = /[\p{L}\p{N}]+/gu;
const MARKS_PATTERN = /\p{M}/gu;
const POSSESSIVE_PATTERN = /'s(?![\p{L}\p{N}])/gu;
const QUOTES_PATTERN = /[’‘`´]/g;
const TAG_PATTERN = /<[^>]*>/g;
const BREAK_TAG_PATTERN =
    /<\/(?:p|li|div|h[1-6]|blockquote|tr|td|th|ul|ol|figcaption|section|pre)>|<br\s*\/?>/gi;
const SPACES_PATTERN = /[ \t ]+/g;
const ENTITY_PATTERN = /&(#x[0-9a-f]+|#\d+|[a-z]+);/gi;
const SENTENCE_END_PATTERN = /[.!?…]+["'”’»)\]]*(?=\s|$)/g;
const LINE_PATTERN = /\n+/;
const INITIAL_PATTERN = /^\p{Lu}$/u;

const NAMED_ENTITIES: Record<string, string> = {
    amp: '&',
    lt: '<',
    gt: '>',
    quot: '"',
    apos: "'",
    nbsp: ' ',
    ndash: '-',
    mdash: '-',
    hellip: '…',
    rsquo: '’',
    lsquo: '‘',
    rdquo: '”',
    ldquo: '“',
    laquo: '«',
    raquo: '»',
    iexcl: '¡',
    iquest: '¿',
};

// Short words that end with a dot but do not end a sentence (lower case, no final dot).
const ABBREVIATIONS = new Set([
    'mr',
    'mrs',
    'ms',
    'dr',
    'prof',
    'st',
    'jr',
    'vs',
    'etc',
    'e.g',
    'i.e',
    'approx',
    'no',
    'fig',
    'sr',
    'sra',
    'srta',
    'dra',
    'ud',
    'uds',
    'p.ej',
    'aprox',
    'av',
    'pág',
    'núm',
]);

// Turns HTML entities like &amp; back into characters.
export function decodeEntities(text: string): string {
    if (!text.includes('&')) {
        return text;
    }

    return text.replace(ENTITY_PATTERN, (entity, name: string) => {
        if (name[0] === '#') {
            const code =
                name[1] === 'x' || name[1] === 'X'
                    ? parseInt(name.slice(2), 16)
                    : parseInt(name.slice(1), 10);

            return Number.isFinite(code) && code > 0 && code <= 0x10ffff
                ? String.fromCodePoint(code)
                : entity;
        }

        return NAMED_ENTITIES[name.toLowerCase()] ?? entity;
    });
}

// Turns inline HTML into plain text. Block tags become new lines.
export function htmlToText(html: string): string {
    if (html === '') {
        return '';
    }

    const plain = decodeEntities(
        html.replace(BREAK_TAG_PATTERN, '\n').replace(TAG_PATTERN, ''),
    );

    return plain
        .split('\n')
        .map((line) => line.replace(SPACES_PATTERN, ' ').trim())
        .filter((line) => line !== '')
        .join('\n');
}

// Lower case, without accents, with straight quotes. Used to compare words.
export function normalize(text: string): string {
    return text
        .normalize('NFD')
        .replace(MARKS_PATTERN, '')
        .toLowerCase()
        .replace(QUOTES_PATTERN, "'");
}

// The words of a text. Keeps "don't", "Lisbon's", "well-known" and "3.5" as one word.
export function wordsOf(text: string): string[] {
    return text.match(WORD_PATTERN) ?? [];
}

// Normalized pieces of a text used to match words ("Lisbon's" gives "lisbon").
export function tokensOf(text: string): string[] {
    return (
        normalize(text).replace(POSSESSIVE_PATTERN, '').match(TOKEN_PATTERN) ??
        []
    );
}

// True when the dot at the end of this word does not end the sentence.
function isAbbreviation(word: string): boolean {
    const bare = word.replace(/^[¿¡("'“«]+/, '').replace(/[.]+$/, '');

    return INITIAL_PATTERN.test(bare) || ABBREVIATIONS.has(bare.toLowerCase());
}

// Splits one line of text into sentences.
function splitLine(line: string, sentences: string[]): void {
    let start = 0;

    for (const match of line.matchAll(SENTENCE_END_PATTERN)) {
        const end = match.index + match[0].length;

        if (match[0][0] === '.' && match[0].length === 1) {
            const before = line.slice(start, match.index + 1);
            const lastWord = before.slice(before.search(/\S+$/));

            if (isAbbreviation(lastWord)) {
                continue;
            }
        }

        const sentence = line.slice(start, end).trim();

        if (sentence !== '') {
            sentences.push(sentence);
        }

        start = end;
    }

    const rest = line.slice(start).trim();

    if (rest !== '') {
        sentences.push(rest);
    }
}

// Splits text into sentences. New lines always end a sentence.
export function splitSentences(text: string): string[] {
    const sentences: string[] = [];

    for (const line of text.split(LINE_PATTERN)) {
        if (line.trim() !== '') {
            splitLine(line, sentences);
        }
    }

    return sentences.filter((sentence) => wordsOf(sentence).length > 0);
}
