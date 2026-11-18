import { describe, expect, it } from 'vitest';
import {
    blockDesign,
    isValidStyleValue,
    parseStyleInput,
    setStyleValue,
    styleValue,
    withDesign,
} from '@/builder/styles';
import { findBlock, replaceBlock } from '@/builder/tree';
import { cssValueRule } from '@/lib/css-value';
import type { Block } from '@/types';

const hero: Block = {
    id: 'b_hero',
    type: 'section',
    attrs: { tag: 'section' },
    content: '',
    children: [
        {
            id: 'b_title',
            type: 'heading',
            attrs: { level: 1 },
            content: 'Fresh bread every morning',
            children: [],
        },
    ],
};

describe('isValidStyleValue', () => {
    it.each([
        ['length', '24px', true],
        ['length', '1.5rem', true],
        ['length', 0, true],
        ['length', 'var:spacing.md', true],
        ['length', '24 px', false],
        ['length', 'calc(1px + 2px)', true],
        ['length', 'calc(1px + url(x))', false],
        ['length', '100dvh', true],
        ['length', 'clamp(1rem, 2vw, 3rem)', true],
        ['angle', '45deg', true],
        ['angle', '45px', false],
        ['time', '200ms', true],
        ['color', '#1f6f5c', true],
        ['color', 'red', false],
        ['color', '#1f6f5c;background:url(x)', false],
        ['number', 1.4, true],
        ['number', 9, false],
        ['weight', 600, true],
        ['weight', 650, false],
        ['columns', 3, true],
        ['columns', 13, false],
        ['hidden', true, true],
        ['justify', 'between', true],
        ['justify', 'space-between', false],
        ['token', 'var:font.heading', true],
        ['token', 'Georgia', false],
    ])('%s %j is %s', (kind, value, valid) => {
        expect(isValidStyleValue(kind, value)).toBe(valid);
    });

    it('uses the value rule the server sent', () => {
        const padding = cssValueRule({ negative: false });
        const lineHeight = cssValueRule({
            keywords: ['normal'],
            unitless: true,
        });

        expect(isValidStyleValue('length', '-1rem', padding)).toBe(false);
        expect(isValidStyleValue('length', '1rem', padding)).toBe(true);
        expect(isValidStyleValue('length', 1.6, lineHeight)).toBe(true);
        expect(isValidStyleValue('length', 'normal', lineHeight)).toBe(true);
        expect(isValidStyleValue('length', 'auto', lineHeight)).toBe(false);
    });
});

describe('parseStyleInput', () => {
    it('turns numbers into numbers only for number kinds', () => {
        expect(parseStyleInput('weight', ' 700 ')).toBe(700);
        expect(parseStyleInput('number', '1.6')).toBe(1.6);
        expect(parseStyleInput('length', '16')).toBe('16');
        expect(parseStyleInput('number', 'var:leading.tight')).toBe(
            'var:leading.tight',
        );
    });
});

describe('setStyleValue and styleValue', () => {
    it('stores a plain value for every screen', () => {
        const design = setStyleValue({}, 'spacing.padding.top', 'base', '4rem');

        expect(design).toEqual({ spacing: { padding: { top: '4rem' } } });
        expect(styleValue(design, 'spacing.padding.top', 'base')).toEqual({
            value: '4rem',
            inherited: false,
        });
    });

    it('turns a value into a responsive one for smaller screens', () => {
        const base = setStyleValue({}, 'typography.fontSize', 'base', '3rem');
        const design = setStyleValue(
            base,
            'typography.fontSize',
            'mobile',
            '2rem',
        );

        expect(design).toEqual({
            typography: { fontSize: { base: '3rem', mobile: '2rem' } },
        });
        expect(styleValue(design, 'typography.fontSize', 'tablet')).toEqual({
            value: '3rem',
            inherited: true,
        });
        expect(styleValue(design, 'typography.fontSize', 'mobile')).toEqual({
            value: '2rem',
            inherited: false,
        });
    });

    it('goes back to a plain value and removes empty groups when cleared', () => {
        const design = {
            typography: { fontSize: { base: '3rem', mobile: '2rem' } },
        };
        const cleared = setStyleValue(
            design,
            'typography.fontSize',
            'mobile',
            undefined,
        );

        expect(cleared).toEqual({ typography: { fontSize: '3rem' } });
        expect(
            setStyleValue(cleared, 'typography.fontSize', 'base', undefined),
        ).toEqual({});
    });

    it('hides a block only on phones', () => {
        const design = setStyleValue({}, 'visibility.hidden', 'mobile', true);

        expect(design).toEqual({ visibility: { hidden: { mobile: true } } });
        expect(styleValue(design, 'visibility.hidden', 'base').value).toBe(
            undefined,
        );
    });

    it('does not change the design it was given', () => {
        const design = { color: { text: '#222222' } };
        setStyleValue(design, 'color.text', 'base', '#ffffff');

        expect(design).toEqual({ color: { text: '#222222' } });
    });
});

describe('block helpers', () => {
    it('reads and writes the design attr', () => {
        const styled = withDesign(hero, { color: { background: '#f4efe6' } });

        expect(blockDesign(styled)).toEqual({
            color: { background: '#f4efe6' },
        });
        expect(withDesign(styled, {}).attrs).toEqual({ tag: 'section' });
        expect(blockDesign(hero)).toEqual({});
    });

    it('finds and replaces nested blocks', () => {
        const title = findBlock([hero], 'b_title')!;
        const next = replaceBlock([hero], 'b_title', {
            ...title,
            content: 'Sourdough on Saturdays',
        });

        expect(findBlock(next, 'b_title')?.content).toBe(
            'Sourdough on Saturdays',
        );
        expect(findBlock([hero], 'missing')).toBeNull();
        expect(replaceBlock([hero], 'missing', title)[0]).toBe(hero);
    });
});
