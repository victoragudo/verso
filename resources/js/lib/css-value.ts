// CSS units and the safe size value check, the same as App\Verso\Core\Css\CssValueParser in PHP.
// tests/Fixtures/css-values.json holds cases that both the PHP and the TS tests check, so the two never drift.

export type CssValueType = 'length' | 'angle' | 'time' | 'number';

// Which values one CSS property accepts (same as CssValueRule::toArray() in PHP).
export type CssValueRule = {
    type: CssValueType;
    units: string[];
    keywords: string[];
    unitless: boolean;
    negative: boolean;
    custom: boolean;
};

// Units most people use, shown first in the unit picker.
export const COMMON_UNITS = ['px', 'rem', 'em', '%', 'vw', 'vh'];

// Length units by group, in the order the unit picker shows them.
export const LENGTH_UNIT_GROUPS: Record<string, string[]> = {
    absolute: ['px', 'cm', 'mm', 'Q', 'in', 'pc', 'pt'],
    font: [
        'em',
        'rem',
        'ex',
        'rex',
        'cap',
        'rcap',
        'ch',
        'rch',
        'ic',
        'ric',
        'lh',
        'rlh',
    ],
    viewport: [
        'vw',
        'vh',
        'vi',
        'vb',
        'vmin',
        'vmax',
        'svw',
        'svh',
        'svi',
        'svb',
        'svmin',
        'svmax',
        'lvw',
        'lvh',
        'lvi',
        'lvb',
        'lvmin',
        'lvmax',
        'dvw',
        'dvh',
        'dvi',
        'dvb',
        'dvmin',
        'dvmax',
    ],
    container: ['cqw', 'cqh', 'cqi', 'cqb', 'cqmin', 'cqmax'],
    percent: ['%'],
};

export const ANGLE_UNITS = ['deg', 'rad', 'grad', 'turn'];

export const TIME_UNITS = ['s', 'ms'];

// Keywords a property may allow next to its units.
export const CSS_KEYWORDS = [
    'auto',
    'fit-content',
    'min-content',
    'max-content',
    'none',
    'normal',
];

// Functions allowed in a custom value.
export const CSS_FUNCTIONS = ['calc', 'clamp', 'min', 'max', 'var'];

// Longest custom value, in characters, and deepest nesting.
export const CSS_VALUE_MAX_LENGTH = 200;
export const CSS_VALUE_MAX_DEPTH = 6;

export const LENGTH_UNITS = Object.values(LENGTH_UNIT_GROUPS).flat();

const ALL_UNITS = [...LENGTH_UNITS, ...ANGLE_UNITS, ...TIME_UNITS];

export function unitsForType(type: CssValueType): string[] {
    switch (type) {
        case 'angle':
            return ANGLE_UNITS;
        case 'time':
            return TIME_UNITS;
        case 'number':
            return [];
        default:
            return LENGTH_UNITS;
    }
}

// The unit as CSS writes it, like "Q" for "q". Null when it is not a unit.
export function canonicalUnit(unit: string): string | null {
    const lower = unit.toLowerCase();

    return ALL_UNITS.find((known) => known.toLowerCase() === lower) ?? null;
}

// A full rule from partial data, like CssValueRule::fromArray() in PHP.
export function cssValueRule(data: Partial<CssValueRule> = {}): CssValueRule {
    const type: CssValueType = ['length', 'angle', 'time', 'number'].includes(
        data.type ?? '',
    )
        ? (data.type as CssValueType)
        : 'length';
    const known = unitsForType(type);

    return {
        type,
        units: data.units
            ? known.filter((unit) => data.units!.includes(unit))
            : known,
        keywords: data.keywords
            ? CSS_KEYWORDS.filter((word) => data.keywords!.includes(word))
            : [],
        unitless: data.unitless ?? type === 'number',
        negative: data.negative ?? true,
        custom: data.custom ?? true,
    };
}

// The same rule with fewer units, like a theme limit.
export function onlyUnits(rule: CssValueRule, units: string[]): CssValueRule {
    return {
        ...rule,
        units: rule.units.filter((unit) => units.includes(unit)),
    };
}

const PLAIN = /^([+-]?)(\d{1,6}(?:\.\d{1,4})?|\.\d{1,4})([a-zA-Z]{1,5}|%)?$/;
const NUMBER = /^(\d{1,6}(?:\.\d{1,4})?|\.\d{1,4})([a-zA-Z]{1,5}|%)?/;
const FUNCTION = /^(calc|clamp|min|max|var)\(/i;
const CUSTOM_PROPERTY = /^--[a-zA-Z_][a-zA-Z0-9_-]{0,59}/;
const SPACE = /^[ \t\n\r\f\v]$/;
const DIGIT = /^[0-9]$/;

type Token = { type: string; text: string; unit?: string };

function plain(
    rawSign: string,
    rawNumber: string,
    rawUnit: string,
    rule: CssValueRule,
): string | null {
    const number = rawNumber.startsWith('.') ? `0${rawNumber}` : rawNumber;
    const zero = Number(number) === 0;
    const sign = rawSign === '-' && !zero ? '-' : '';

    if (sign === '-' && !rule.negative) {
        return null;
    }

    if (rawUnit === '') {
        if (rule.unitless) {
            return sign + number;
        }

        if (zero && rule.type === 'length') {
            return '0';
        }

        return rule.type === 'length' && rule.units.includes('px')
            ? `${sign}${number}px`
            : null;
    }

    const unit = canonicalUnit(rawUnit);

    return unit !== null && rule.units.includes(unit)
        ? sign + number + unit
        : null;
}

function tokenize(text: string): Token[] | null {
    const tokens: Token[] = [];
    let offset = 0;

    while (offset < text.length) {
        const rest = text.slice(offset);
        const char = text[offset];
        const next = text[offset + 1] ?? '';
        const previous = tokens.length ? tokens[tokens.length - 1].type : null;
        let match: RegExpExecArray | null;

        if (SPACE.test(char)) {
            offset++;
        } else if ((match = FUNCTION.exec(rest))) {
            tokens.push({ type: 'function', text: match[1].toLowerCase() });
            offset += match[0].length;
        } else if ((match = CUSTOM_PROPERTY.exec(rest))) {
            tokens.push({ type: 'property', text: match[0] });
            offset += match[0].length;
        } else if (
            (char === '-' || char === '+') &&
            (DIGIT.test(next) || next === '.') &&
            [null, 'operator', 'open', 'comma', 'function'].includes(previous)
        ) {
            match = NUMBER.exec(text.slice(offset + 1));

            if (!match) {
                return null;
            }

            tokens.push({
                type: 'number',
                text: (char === '-' ? '-' : '') + match[1],
                unit: match[2] ?? '',
            });
            offset += 1 + match[0].length;
        } else if ((match = NUMBER.exec(rest))) {
            tokens.push({
                type: 'number',
                text: match[1],
                unit: match[2] ?? '',
            });
            offset += match[0].length;
        } else if (['+', '-', '*', '/'].includes(char)) {
            tokens.push({ type: 'operator', text: char });
            offset++;
        } else if (char === '(' || char === ')' || char === ',') {
            tokens.push({
                type: char === ',' ? 'comma' : char === '(' ? 'open' : 'close',
                text: char,
            });
            offset++;
        } else {
            return null;
        }
    }

    return tokens;
}

// Reads a custom value: one function call like clamp(1rem, 2vw + 1rem, 3rem), in a clean form.
class CustomValueReader {
    private position = 0;

    constructor(
        private readonly tokens: Token[],
        private readonly rule: CssValueRule,
    ) {}

    read(): string | null {
        if (this.tokens.length === 0 || this.tokens[0].type !== 'function') {
            return null;
        }

        const css = this.call(1);

        return css !== null && this.position === this.tokens.length
            ? css
            : null;
    }

    private peek(): Token | undefined {
        return this.tokens[this.position];
    }

    private take(type: string): boolean {
        if (this.peek()?.type !== type) {
            return false;
        }

        this.position++;

        return true;
    }

    private call(depth: number): string | null {
        const token = this.peek();

        if (depth > CSS_VALUE_MAX_DEPTH || token?.type !== 'function') {
            return null;
        }

        this.position++;

        if (token.text === 'var') {
            const property = this.peek();

            if (!this.take('property') || !property) {
                return null;
            }

            let fallback: string | null = null;

            if (this.take('comma')) {
                fallback = this.sum(depth + 1);

                if (fallback === null) {
                    return null;
                }
            }

            return this.take('close')
                ? `var(${property.text}${fallback !== null ? `, ${fallback}` : ''})`
                : null;
        }

        const args: string[] = [];

        do {
            const arg = this.sum(depth + 1);

            if (arg === null) {
                return null;
            }

            args.push(arg);
        } while (this.take('comma'));

        const fits =
            token.text === 'calc'
                ? args.length === 1
                : token.text === 'clamp'
                  ? args.length === 3
                  : true;

        return fits && this.take('close')
            ? `${token.text}(${args.join(', ')})`
            : null;
    }

    private joined(
        depth: number,
        operators: string[],
        part: (depth: number) => string | null,
    ): string | null {
        let css = part(depth);

        while (
            css !== null &&
            this.peek()?.type === 'operator' &&
            operators.includes(this.peek()!.text)
        ) {
            const operator = this.tokens[this.position++].text;
            const right = part(depth);
            css = right === null ? null : `${css} ${operator} ${right}`;
        }

        return css;
    }

    private sum(depth: number): string | null {
        return this.joined(depth, ['+', '-'], (d) => this.product(d));
    }

    private product(depth: number): string | null {
        return this.joined(depth, ['*', '/'], (d) => this.term(d));
    }

    private term(depth: number): string | null {
        const token = this.peek();

        if (!token) {
            return null;
        }

        if (token.type === 'function') {
            return this.call(depth);
        }

        if (token.type === 'open') {
            this.position++;
            const inner =
                depth >= CSS_VALUE_MAX_DEPTH ? null : this.sum(depth + 1);

            return inner !== null && this.take('close') ? `(${inner})` : null;
        }

        if (token.type !== 'number') {
            return null;
        }

        this.position++;
        const number = token.text.replace(/^(-?)\./, '$10.');

        if (!token.unit) {
            return number;
        }

        const unit = canonicalUnit(token.unit);

        return unit !== null && this.rule.units.includes(unit)
            ? number + unit
            : null;
    }
}

// The clean CSS value, or null when it is not allowed. Old plain numbers on a length become pixels, like 24 => "24px".
export function normalizeCssValue(
    value: unknown,
    rule: CssValueRule,
): string | null {
    const raw =
        typeof value === 'number' && Number.isFinite(value)
            ? String(value)
            : value;

    if (typeof raw !== 'string') {
        return null;
    }

    const text = raw.trim();

    if (text === '' || text.length > CSS_VALUE_MAX_LENGTH) {
        return null;
    }

    if (rule.keywords.includes(text.toLowerCase())) {
        return text.toLowerCase();
    }

    const match = PLAIN.exec(text);

    if (match) {
        return plain(match[1], match[2], match[3] ?? '', rule);
    }

    if (!rule.custom) {
        return null;
    }

    const tokens = tokenize(text);

    return tokens ? new CustomValueReader(tokens, rule).read() : null;
}

export function isValidCssValue(value: unknown, rule: CssValueRule): boolean {
    return normalizeCssValue(value, rule) !== null;
}

// The parts of a value for the control: a number and its unit, a keyword, or a custom value.
export type CssValueParts =
    | { mode: 'empty' }
    | { mode: 'number'; number: string; unit: string }
    | { mode: 'keyword'; keyword: string }
    | { mode: 'custom'; text: string };

// Splits a value into the parts the control shows. Plain old numbers get their px unit.
export function splitCssValue(
    value: unknown,
    rule: CssValueRule,
): CssValueParts {
    const css = normalizeCssValue(value, rule);

    if (css === null) {
        return typeof value === 'string' && value.trim() !== ''
            ? { mode: 'custom', text: value.trim() }
            : { mode: 'empty' };
    }

    if (rule.keywords.includes(css)) {
        return { mode: 'keyword', keyword: css };
    }

    const match = PLAIN.exec(css);

    if (match) {
        return {
            mode: 'number',
            number: (match[1] === '-' ? '-' : '') + match[2],
            unit: match[3] ?? '',
        };
    }

    return { mode: 'custom', text: css };
}

// How much one arrow key press changes a value in this unit. Shift makes it ten times bigger.
export function unitStep(unit: string): number {
    if (['px', 'pt', 'Q', 'mm', '%', 'deg', 'grad', 'ms'].includes(unit)) {
        return 1;
    }

    if (
        ['cm', 'in', 'pc', 'rad', 's'].includes(unit) ||
        LENGTH_UNIT_GROUPS.font.includes(unit)
    ) {
        return 0.1;
    }

    if (unit === 'turn') {
        return 0.05;
    }

    return unit === '' ? 0.1 : 1;
}

function decimals(number: number | string): number {
    const text = String(number);

    return text.includes('.') ? text.split('.')[1].length : 0;
}

// The number after one arrow key press, without float noise like 0.30000000000000004.
export function stepNumber(
    number: string,
    unit: string,
    direction: 1 | -1,
    big = false,
): string {
    const step = unitStep(unit) * (big ? 10 : 1);
    const current = Number(number === '' || number === '-' ? 0 : number);
    const places = Math.min(4, Math.max(decimals(step), decimals(number)));
    const next = Number((current + direction * step).toFixed(places));

    return String(Object.is(next, -0) ? 0 : next);
}
