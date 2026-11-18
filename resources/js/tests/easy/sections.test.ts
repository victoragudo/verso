import { describe, expect, it } from 'vitest';
import {
    categoryLabel,
    filterSections,
    sectionCategories,
} from '@/lib/easy/sections';
import { coreSections } from './page-fixtures';

describe('section gallery search', () => {
    it('lists categories once, in order, with plain names', () => {
        expect(sectionCategories(coreSections)).toEqual([
            { value: 'intro', label: 'Welcome' },
            { value: 'sales', label: 'Prices' },
            { value: 'help', label: 'Help' },
            { value: 'contact', label: 'Contact' },
        ]);
        expect(categoryLabel('opening-hours')).toBe('Opening hours');
    });

    it('finds sections by words in any order, without accents or case', () => {
        expect(
            filterSections(coreSections, 'PREGUNTAS', null).map(
                (item) => item.key,
            ),
        ).toEqual(['faq']);
        expect(
            filterSections(coreSections, 'banner welcome', null).map(
                (item) => item.key,
            ),
        ).toEqual(['hero']);
        expect(
            filterSections(coreSections, 'Mápa', null).map((item) => item.key),
        ).toEqual(['map']);
        expect(filterSections(coreSections, '  ', null)).toHaveLength(5);
        expect(filterSections(coreSections, 'fireworks', null)).toEqual([]);
    });

    it('limits the list to one category', () => {
        expect(
            filterSections(coreSections, '', 'contact').map((item) => item.key),
        ).toEqual(['map', 'contact']);
        expect(
            filterSections(coreSections, 'form', 'contact').map(
                (item) => item.key,
            ),
        ).toEqual(['contact']);
    });
});
