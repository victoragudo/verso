import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { contrastRatio } from '@/lib/easy/contrast';
import {
    fixSectionColors,
    readableTextColor,
    resolveColor,
    reviewSectionColors,
    SECTION_COLOR_MESSAGE,
} from '@/lib/easy/section-colors';
import type { SiteColors } from '@/lib/easy/section-colors';
import type { Block } from '@/types';

type SectionCase = {
    name: string;
    blocks: Block[];
    warnings: {
        sectionId: string;
        groups: {
            originId: string;
            foreground: string;
            background: string;
            minimum: number;
        }[];
    }[];
    fixed: Record<string, string>;
};

const fixture = JSON.parse(
    readFileSync(
        resolve(__dirname, '../../../../tests/Fixtures/contrast.json'),
        'utf8',
    ),
) as { sections: { colors: SiteColors; cases: SectionCase[] } };

const { colors, cases } = fixture.sections;

function findBlock(blocks: Block[], id: string): Block | undefined {
    for (const block of blocks) {
        const found =
            block.id === id ? block : findBlock(block.children ?? [], id);

        if (found) {
            return found;
        }
    }

    return undefined;
}

function textColor(block: Block | undefined): unknown {
    const design = block?.attrs?.design as
        | { color?: { text?: unknown } }
        | undefined;
    const value = design?.color?.text;

    return value !== null && typeof value === 'object' && 'base' in value
        ? (value as { base: unknown }).base
        : value;
}

describe('section colors', () => {
    it.each(cases.map((item) => [item.name, item] as const))(
        'warns like the shared fixture: %s',
        (_name, item) => {
            const warnings = reviewSectionColors(item.blocks, colors);

            expect(
                warnings.map((warning) => ({
                    sectionId: warning.sectionId,
                    groups: warning.groups.map(
                        ({ originId, foreground, background, minimum }) => ({
                            originId,
                            foreground,
                            background,
                            minimum,
                        }),
                    ),
                })),
            ).toEqual(item.warnings);
            warnings.forEach((warning) =>
                expect(warning.message).toBe(SECTION_COLOR_MESSAGE),
            );
        },
    );

    it.each(cases.map((item) => [item.name, item] as const))(
        'fixes like the shared fixture: %s',
        (_name, item) => {
            let blocks = item.blocks;

            for (const warning of reviewSectionColors(blocks, colors)) {
                blocks = fixSectionColors(blocks, warning, colors);
            }

            for (const [id, value] of Object.entries(item.fixed)) {
                expect(textColor(findBlock(blocks, id))).toBe(value);
            }

            expect(reviewSectionColors(blocks, colors)).toEqual([]);
        },
    );

    it('keeps other screen sizes and style options when it fixes a color', () => {
        const [, pale] = cases;
        const [warning] = reviewSectionColors(pale.blocks, colors);
        const fixed = fixSectionColors(pale.blocks, warning, colors);
        const heading = findBlock(fixed, 'b_title002');

        expect(heading?.attrs.design).toEqual({
            color: { text: { base: 'var:color.text', mobile: '#1b2420' } },
        });
        expect(heading?.attrs.level).toBe(2);
        expect(pale.blocks[0].children?.[0].attrs.design).toEqual({
            color: { text: { base: '#e2dfd2', mobile: '#1b2420' } },
        });
    });

    it('reads tokens, short hex colors and responsive values', () => {
        expect(resolveColor('var:color.accent', colors)).toBe('#2e5e4e');
        expect(resolveColor('#FFF', colors)).toBe('#ffffff');
        expect(resolveColor({ base: 'var:color.text' }, colors)).toBe(
            '#1b2420',
        );
        expect(resolveColor('var:color.brand-wash', colors)).toBeUndefined();
        expect(
            resolveColor('linear-gradient(red, blue)', colors),
        ).toBeUndefined();
    });

    it('makes a new color when no color of the style is readable', () => {
        const grey = { 'color.text': '#777777', 'color.background': '#808080' };
        const picked = readableTextColor(
            { foreground: '#777777', background: '#808080', minimum: 4.5 },
            grey,
        );

        expect(picked).toMatch(/^#[0-9a-f]{6}$/);
        expect(contrastRatio(picked, '#808080')).toBeGreaterThanOrEqual(4.5);
    });

    it('checks nothing on a site without text and background colors', () => {
        expect(reviewSectionColors(cases[0].blocks, {})).toEqual([]);
    });
});
