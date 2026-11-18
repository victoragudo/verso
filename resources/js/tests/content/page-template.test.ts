import { describe, expect, it } from 'vitest';
import {
    DEFAULT_TEMPLATE,
    templateFromMeta,
    templateOptions,
} from '@/components/verso/content/page-template';
import type { PageTemplateOption } from '@/types';

const templates: PageTemplateOption[] = [
    {
        value: 'full-width',
        label: 'Full width',
        description: 'Text uses the whole layout width.',
    },
    { value: 'landing', label: 'Landing page', description: '' },
];

describe('templateOptions', () => {
    it('starts with the default template and lists the theme templates', () => {
        expect(templateOptions(templates, DEFAULT_TEMPLATE)).toEqual([
            { value: 'default', label: 'Default template' },
            {
                value: 'full-width',
                label: 'Full width',
                keywords: ['Text uses the whole layout width.'],
            },
            { value: 'landing', label: 'Landing page', keywords: [] },
        ]);
    });

    it('keeps a stored template the theme no longer offers', () => {
        const options = templateOptions(templates, 'retired-landing');

        expect(options).toHaveLength(4);
        expect(options[3]).toEqual({
            value: 'retired-landing',
            label: 'retired-landing',
            description: 'Not in the active theme',
        });
    });
});

describe('templateFromMeta', () => {
    it('reads the template name from meta', () => {
        expect(templateFromMeta({ template: 'landing' })).toBe('landing');
    });

    it('uses the default for missing, empty or broken values', () => {
        expect(templateFromMeta(undefined)).toBe(DEFAULT_TEMPLATE);
        expect(templateFromMeta({})).toBe(DEFAULT_TEMPLATE);
        expect(templateFromMeta({ template: '' })).toBe(DEFAULT_TEMPLATE);
        expect(templateFromMeta({ template: ['landing'] })).toBe(
            DEFAULT_TEMPLATE,
        );
    });
});
