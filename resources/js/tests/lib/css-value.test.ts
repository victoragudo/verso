import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import {
    ANGLE_UNITS,
    canonicalUnit,
    COMMON_UNITS,
    CSS_FUNCTIONS,
    CSS_KEYWORDS,
    CSS_VALUE_MAX_DEPTH,
    CSS_VALUE_MAX_LENGTH,
    cssValueRule,
    isValidCssValue,
    LENGTH_UNIT_GROUPS,
    normalizeCssValue,
    onlyUnits,
    splitCssValue,
    stepNumber,
    TIME_UNITS,
    unitStep,
} from '@/lib/css-value';
import type { CssValueRule } from '@/lib/css-value';

type Fixture = {
    units: {
        common: string[];
        lengthGroups: Record<string, string[]>;
        angle: string[];
        time: string[];
        keywords: string[];
        functions: string[];
        maxLength: number;
        maxDepth: number;
    };
    rules: Record<string, Partial<CssValueRule>>;
    cases: { name: string; rule: string; value: unknown; css: string | null }[];
};

// The same cases the PHP test reads, so the editor and the server accept the same values.
const fixture = JSON.parse(
    readFileSync(
        resolve(process.cwd(), 'tests/Fixtures/css-values.json'),
        'utf8',
    ),
) as Fixture;

describe('shared unit lists', () => {
    it('match the PHP lists', () => {
        expect(COMMON_UNITS).toEqual(fixture.units.common);
        expect(LENGTH_UNIT_GROUPS).toEqual(fixture.units.lengthGroups);
        expect(ANGLE_UNITS).toEqual(fixture.units.angle);
        expect(TIME_UNITS).toEqual(fixture.units.time);
        expect(CSS_KEYWORDS).toEqual(fixture.units.keywords);
        expect(CSS_FUNCTIONS).toEqual(fixture.units.functions);
        expect(CSS_VALUE_MAX_LENGTH).toBe(fixture.units.maxLength);
        expect(CSS_VALUE_MAX_DEPTH).toBe(fixture.units.maxDepth);
    });
});

describe('normalizeCssValue', () => {
    it.each(fixture.cases)('$name', ({ rule, value, css }) => {
        expect(
            normalizeCssValue(value, cssValueRule(fixture.rules[rule])),
        ).toBe(css);
    });

    it('gives the same answer for its own output', () => {
        fixture.cases
            .filter((item) => item.css !== null)
            .forEach(({ rule, css }) => {
                expect(
                    normalizeCssValue(css, cssValueRule(fixture.rules[rule])),
                ).toBe(css);
            });
    });
});

describe('cssValueRule', () => {
    it('fills every unit of the type when none are named', () => {
        expect(cssValueRule({ type: 'angle' }).units).toEqual(ANGLE_UNITS);
        expect(cssValueRule({ type: 'number' })).toMatchObject({
            units: [],
            unitless: true,
        });
    });

    it('drops unknown units and keywords and keeps the known order', () => {
        expect(
            cssValueRule({
                units: ['rem', 'furlong', 'px'],
                keywords: ['auto', 'inherit'],
            }),
        ).toMatchObject({ units: ['px', 'rem'], keywords: ['auto'] });
    });

    it('limits units for a theme', () => {
        const rule = onlyUnits(cssValueRule(), ['rem', '%']);

        expect(rule.units).toEqual(['rem', '%']);
        expect(isValidCssValue('12px', rule)).toBe(false);
        expect(isValidCssValue('1.5rem', rule)).toBe(true);
    });
});

describe('canonicalUnit', () => {
    it.each([
        ['PX', 'px'],
        ['q', 'Q'],
        ['DvH', 'dvh'],
        ['furlong', null],
    ])('%s gives %s', (unit, expected) => {
        expect(canonicalUnit(unit)).toBe(expected);
    });
});

describe('splitCssValue', () => {
    const width = cssValueRule({ keywords: ['auto'], negative: false });

    it.each([
        [undefined, { mode: 'empty' }],
        ['', { mode: 'empty' }],
        ['1.5rem', { mode: 'number', number: '1.5', unit: 'rem' }],
        [24, { mode: 'number', number: '24', unit: 'px' }],
        ['0', { mode: 'number', number: '0', unit: '' }],
        ['auto', { mode: 'keyword', keyword: 'auto' }],
        [
            'clamp(1rem, 2vw, 3rem)',
            { mode: 'custom', text: 'clamp(1rem, 2vw, 3rem)' },
        ],
        ['12 apples', { mode: 'custom', text: '12 apples' }],
    ])('%j', (value, parts) => {
        expect(splitCssValue(value, width)).toEqual(parts);
    });

    it('keeps the sign of a negative number', () => {
        expect(splitCssValue('-0.5em', cssValueRule())).toEqual({
            mode: 'number',
            number: '-0.5',
            unit: 'em',
        });
    });
});

describe('stepNumber', () => {
    it.each([
        ['16', 'px', 1, false, '17'],
        ['16', 'px', -1, true, '6'],
        ['1.5', 'rem', 1, false, '1.6'],
        ['0.2', 'rem', 1, false, '0.3'],
        ['0.1', 'em', -1, false, '0'],
        ['1', 'rem', 1, true, '2'],
        ['', '%', 1, false, '1'],
        ['0.25', 'turn', 1, false, '0.3'],
        ['200', 'ms', -1, true, '190'],
        ['1.25', '', 1, false, '1.35'],
    ] as const)(
        '%s%s %i (shift %s) gives %s',
        (number, unit, direction, big, expected) => {
            expect(stepNumber(number, unit, direction, big)).toBe(expected);
        },
    );

    it('uses bigger steps for pixels than for rem', () => {
        expect(unitStep('px')).toBeGreaterThan(unitStep('rem'));
    });
});
