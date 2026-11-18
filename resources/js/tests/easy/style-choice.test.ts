import { describe, expect, it } from 'vitest';
import {
    choiceTokens,
    previewSrc,
    suggestedFontPair,
} from '@/lib/easy/style-choice';
import {
    boldPreset,
    classicPair,
    calmPreset,
    modernPair,
    roles,
} from './style-fixtures';

describe('choiceTokens', () => {
    it('joins preset, font pair and color tokens, colors last', () => {
        expect(
            choiceTokens(
                {
                    colors: {
                        accent: '#1f4e79',
                        text: '#1b2420',
                        surface: '#eef2f6',
                    },
                    preset: calmPreset,
                    fontPair: classicPair,
                },
                roles.filter((role) => role.role !== 'surface'),
            ),
        ).toEqual({
            'radius.base': '6px',
            'spacing.small': '0.5rem',
            'font.heading': 'georgia',
            'font.body': 'charter',
            'color.accent': '#1f4e79',
            'color.text': '#1b2420',
        });
    });

    it('works without a preset or font pair', () => {
        expect(
            choiceTokens({ colors: {}, preset: null, fontPair: null }, roles),
        ).toEqual({});
    });
});

describe('previewSrc', () => {
    const base = '/admin/site-editor/preview';

    it('adds every token to the preview address', () => {
        expect(
            previewSrc(base, {
                'color.accent': '#1f4e79',
                'radius.base': '6px',
            }),
        ).toBe(
            '/admin/site-editor/preview?tokens%5Bcolor.accent%5D=%231f4e79&tokens%5Bradius.base%5D=6px',
        );
    });

    it('adds the logo only when it changed, with 0 for no logo', () => {
        expect(previewSrc(base, {})).toBe(base);
        expect(previewSrc(base, {}, 42)).toBe(`${base}?logo=42`);
        expect(previewSrc(base, {}, null)).toBe(`${base}?logo=0`);
        expect(previewSrc(`${base}?styles%5Bheader%5D=centered`, {}, 7)).toBe(
            `${base}?styles%5Bheader%5D=centered&logo=7`,
        );
    });
});

describe('suggestedFontPair', () => {
    const pairs = [classicPair, modernPair];

    it('takes the font pair of the style while the person has not picked fonts', () => {
        expect(suggestedFontPair(boldPreset, pairs, false)).toBe('modern');
    });

    it('keeps the font pair the person picked', () => {
        expect(suggestedFontPair(boldPreset, pairs, true)).toBeNull();
    });

    it('ignores a font pair the theme does not offer', () => {
        expect(suggestedFontPair(boldPreset, [classicPair], false)).toBeNull();
        expect(suggestedFontPair(undefined, pairs, false)).toBeNull();
    });
});
