import { afterEach, describe, expect, it } from 'vitest';
import { labelInSentence } from '@/components/verso/editor/blocks/shared';
import { setMessages } from '@/lib/i18n';

describe('labelInSentence', () => {
    afterEach(() => setMessages('en', {}));

    it('lowercases an English label for use inside a sentence', () => {
        expect(labelInSentence('Quote')).toBe('quote');
    });

    it('translates the label before lowercasing it', () => {
        setMessages('es', { Separator: 'Separador' });

        expect(labelInSentence('Separator')).toBe('separador');
    });

    it('keeps German nouns capitalized', () => {
        setMessages('de', { Image: 'Bild' });

        expect(labelInSentence('Image')).toBe('Bild');
    });
});
