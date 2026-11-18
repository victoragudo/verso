import { describe, expect, it } from 'vitest';
import { formatMessage, messageArguments } from '@/lib/i18n';

describe('formatMessage', () => {
    it('returns plain text as it is', () => {
        expect(formatMessage('Publish now')).toBe('Publish now');
    });

    it('fills arguments', () => {
        expect(
            formatMessage('Saved “{title}”.', { title: 'Autumn menu' }),
        ).toBe('Saved “Autumn menu”.');
    });

    it('keeps unknown arguments visible', () => {
        expect(formatMessage('Hello {name}')).toBe('Hello {name}');
    });

    it('formats numbers in the language', () => {
        expect(formatMessage('{count} visits', { count: 12500 }, 'de')).toBe(
            '12.500 visits',
        );
    });

    it.each([
        ['en', 0, 'No comments'],
        ['en', 1, '1 comment'],
        ['en', 7, '7 comments'],
        ['fr', 1, '1 comment'],
    ])('picks the plural form (%s %d)', (locale, count, expected) => {
        expect(
            formatMessage(
                '{count, plural, =0 {No comments} one {# comment} other {# comments}}',
                { count },
                locale,
            ),
        ).toBe(expected);
    });

    it('uses the plural rules of Russian and Arabic', () => {
        const ru =
            '{n, plural, one {# запись} few {# записи} many {# записей} other {# записи}}';
        const ar =
            '{n, plural, zero {لا شيء} one {واحد} two {اثنان} few {# قليل} many {# كثير} other {# آخر}}';

        expect(formatMessage(ru, { n: 21 }, 'ru')).toBe('21 запись');
        expect(formatMessage(ru, { n: 3 }, 'ru')).toBe('3 записи');
        expect(formatMessage(ru, { n: 25 }, 'ru')).toBe('25 записей');
        expect(formatMessage(ar, { n: 2 }, 'ar')).toBe('اثنان');
        expect(formatMessage(ar, { n: 0 }, 'ar')).toBe('لا شيء');
    });

    it('supports select with nested arguments', () => {
        const message =
            '{status, select, published {Live since {date}} draft {Draft} other {Unknown}}';

        expect(
            formatMessage(message, { status: 'published', date: 'May 3' }),
        ).toBe('Live since May 3');
        expect(formatMessage(message, { status: 'scheduled' })).toBe('Unknown');
    });

    it('supports ordinals', () => {
        expect(
            formatMessage(
                '{n, selectordinal, one {#st} two {#nd} few {#rd} other {#th}}',
                { n: 22 },
            ),
        ).toBe('22nd');
    });

    it('reads apostrophes like ICU', () => {
        expect(formatMessage("It''s '{literal}' and l'article")).toBe(
            "It's {literal} and l'article",
        );
    });

    it('keeps broken messages readable', () => {
        expect(formatMessage('Broken {count, plural')).toContain('Broken');
    });

    it('lists the arguments of a message', () => {
        expect(
            messageArguments(
                '{count, plural, one {# file in {folder}} other {# files}} by {user}',
            ),
        ).toEqual(['count', 'folder', 'user']);
    });
});
