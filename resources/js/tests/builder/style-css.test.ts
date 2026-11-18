import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import {
    blockDeclarations,
    deviceDeclarations,
    sanitizeCustomCss,
    siteRules,
    styleCssValue,
} from '@/builder/style-css';
import type { StyleOptions } from '@/builder/style-css';

type Fixture = {
    breakpoints: Record<string, number>;
    customCssMax: number;
    options: StyleOptions;
    presets: Record<string, Record<string, unknown>>;
    cases: {
        name: string;
        attrs: Record<string, unknown>;
        css: string | null;
    }[];
};

// The same cases the PHP test reads, so the editor and the site make the same CSS.
const fixture = JSON.parse(
    readFileSync(
        resolve(process.cwd(), 'tests/Fixtures/block-styles.json'),
        'utf8',
    ),
) as Fixture;

describe('blockDeclarations and siteRules', () => {
    it.each(fixture.cases)('$name', ({ attrs, css }) => {
        const declarations = blockDeclarations(
            attrs,
            fixture.options,
            fixture.customCssMax,
            fixture.presets,
        );
        const rules = siteRules('vs-x', declarations, fixture.breakpoints);

        expect(rules === '' ? null : rules).toBe(css);
    });
});

describe('deviceDeclarations', () => {
    const declarations = blockDeclarations(
        {
            design: {
                spacing: {
                    padding: {
                        top: { base: '6rem', tablet: '4rem', mobile: '2.5rem' },
                    },
                },
                layout: { columns: { base: 3, mobile: 1 } },
            },
        },
        fixture.options,
        fixture.customCssMax,
    );

    it('keeps only the base values on a computer', () => {
        expect(deviceDeclarations(declarations, 'base')).toEqual([
            'padding-top:6rem',
            'grid-template-columns:repeat(3, minmax(0, 1fr))',
        ]);
    });

    it('puts smaller screen values last so they win on a phone', () => {
        expect(deviceDeclarations(declarations, 'mobile')).toEqual([
            'padding-top:6rem',
            'grid-template-columns:repeat(3, minmax(0, 1fr))',
            'padding-top:4rem',
            'padding-top:2.5rem',
            'grid-template-columns:repeat(1, minmax(0, 1fr))',
        ]);
    });

    it('treats an unknown screen size as every screen', () => {
        expect(deviceDeclarations(declarations, 'watch')).toEqual(
            deviceDeclarations(declarations, 'base'),
        );
    });
});

describe('styleCssValue', () => {
    it.each([
        ['length', '1.5rem', '1.5rem'],
        ['length', 0, '0'],
        ['length', '12 apples', null],
        ['length', 24, '24px'],
        ['length', 'CLAMP(1rem,2vw,3rem)', 'clamp(1rem, 2vw, 3rem)'],
        ['angle', '0.25TURN', '0.25turn'],
        ['time', '150ms', '150ms'],
        ['color', '#1F6F5C', '#1f6f5c'],
        ['color', 'red', null],
        ['color', 'var:color.accent', 'var(--verso-color-accent)'],
        ['weight', 650, null],
        ['columns', 13, null],
        ['justify', 'between', 'space-between'],
        ['token', 'Georgia', null],
        ['hidden', false, ''],
    ])('%s %j gives %j', (kind, value, expected) => {
        expect(styleCssValue(kind, value)).toBe(expected);
    });
});

describe('styleCssValue with a value rule', () => {
    it('applies the keywords and limits the server sent', () => {
        const maxWidth = { keywords: ['none'], negative: false };

        expect(styleCssValue('length', 'none', maxWidth)).toBe('none');
        expect(styleCssValue('length', 'auto', maxWidth)).toBeNull();
        expect(styleCssValue('length', '-2rem', maxWidth)).toBeNull();
        expect(styleCssValue('length', '1.4', { unitless: true })).toBe('1.4');
    });
});

describe('sanitizeCustomCss', () => {
    it('cuts the text at the limit before it reads the lines', () => {
        expect(
            sanitizeCustomCss('color: #14532d; outline: 1px solid', 15),
        ).toBe('color:#14532d');
    });

    it('drops lines that try to leave the rule', () => {
        expect(sanitizeCustomCss('color: red} body {display:none', 2000)).toBe(
            '',
        );
    });
});
