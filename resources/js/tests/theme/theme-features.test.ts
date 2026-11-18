import { describe, expect, it } from 'vitest';
import { themeFeatures } from '@/lib/theme-features';
import type { ThemeInfo } from '@/types';

const harbor: ThemeInfo = {
    slug: 'harbor-dusk',
    name: 'Harbor Dusk',
    version: '1.2.0',
    description: 'Evening colors for the Harbor theme.',
    author: 'Linnea Berg',
    active: false,
    removable: true,
    customizable: true,
    parent: 'harbor',
    parentName: 'Harbor',
    missingParent: null,
    templates: 0,
    patterns: 0,
    parts: 0,
    risk: null,
};

describe('themeFeatures', () => {
    it('is empty when the theme ships nothing extra', () => {
        expect(themeFeatures(harbor)).toBe('');
    });

    it('counts templates, patterns and parts with plurals', () => {
        expect(
            themeFeatures({ ...harbor, templates: 1, patterns: 3, parts: 2 }),
        ).toBe('1 template · 3 patterns · 2 parts');
        expect(themeFeatures({ ...harbor, parts: 1 })).toBe('1 part');
    });
});
