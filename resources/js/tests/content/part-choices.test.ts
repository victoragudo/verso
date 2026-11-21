import { describe, expect, it } from 'vitest';
import {
    INHERIT_STYLE,
    inheritedLabel,
    partErrors,
    partsForMeta,
    partsFromMeta,
} from '@/components/verso/content/part-choices';

const header = {
    name: 'header',
    label: 'Header',
    default: 'classic',
    options: [
        { value: 'classic', label: 'Classic', description: '' },
        { value: 'minimal', label: 'Minimal', description: '' },
    ],
};

describe('part choices', () => {
    it('reads only text styles from meta', () => {
        expect(
            partsFromMeta({
                parts: { header: 'minimal', footer: '', side: 3 },
            }),
        ).toEqual({ header: 'minimal' });
        expect(partsFromMeta({ parts: ['minimal'] })).toEqual({});
        expect(partsFromMeta(undefined)).toEqual({});
    });

    it('leaves inherited parts out of the saved meta', () => {
        expect(
            partsForMeta({ header: 'minimal', footer: INHERIT_STYLE }),
        ).toEqual({ header: 'minimal' });
        expect(partsForMeta({ header: INHERIT_STYLE })).toBeUndefined();
    });

    it('names the inherited style', () => {
        expect(inheritedLabel(header)).toBe('Classic');
        expect(inheritedLabel(header, 'minimal')).toBe('Minimal');
        expect(inheritedLabel(header, 'retired')).toBe('retired');
    });

    it('maps errors to their part', () => {
        expect(
            partErrors({
                'meta.parts.header': 'Choose a header or footer style.',
                title: 'Add a title.',
            }),
        ).toEqual({ header: 'Choose a header or footer style.' });
    });
});
