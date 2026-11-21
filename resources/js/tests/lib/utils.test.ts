import { describe, expect, it } from 'vitest';
import { plainText } from '@/lib/utils';

describe('plainText', () => {
    it('keeps strings, numbers and booleans', () => {
        expect(plainText('Harbor Light')).toBe('Harbor Light');
        expect(plainText(42)).toBe('42');
        expect(plainText(false)).toBe('false');
    });

    it('gives an empty string for null and undefined', () => {
        expect(plainText(null)).toBe('');
        expect(plainText(undefined)).toBe('');
    });

    it('writes objects and arrays as JSON', () => {
        expect(plainText({ tone: 'warning' })).toBe('{"tone":"warning"}');
        expect(plainText(['news', 'events'])).toBe('["news","events"]');
    });
});
