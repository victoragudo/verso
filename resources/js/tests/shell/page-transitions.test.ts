import { describe, expect, it } from 'vitest';
import { opensAnotherPage } from '@/lib/page-transitions';

const current = new URL(
    'https://journal.example/admin/entries/post?status=draft',
);

function visit(
    overrides: Partial<Parameters<typeof opensAnotherPage>[0]> = {},
) {
    return {
        method: 'get' as const,
        url: new URL('https://journal.example/admin/media'),
        preserveState: false,
        only: [],
        prefetch: false,
        ...overrides,
    };
}

describe('opensAnotherPage', () => {
    it('is true for a link to another admin page', () => {
        expect(opensAnotherPage(visit(), current)).toBe(true);
    });

    it('is false for a search or filter on the same page', () => {
        expect(
            opensAnotherPage(
                visit({
                    url: new URL(
                        'https://journal.example/admin/entries/post?status=published',
                    ),
                }),
                current,
            ),
        ).toBe(false);
    });

    it('is false for form posts, kept state, partial reloads and prefetch', () => {
        expect(opensAnotherPage(visit({ method: 'post' }), current)).toBe(
            false,
        );
        expect(opensAnotherPage(visit({ preserveState: true }), current)).toBe(
            false,
        );
        expect(opensAnotherPage(visit({ only: ['entries'] }), current)).toBe(
            false,
        );
        expect(opensAnotherPage(visit({ prefetch: true }), current)).toBe(
            false,
        );
    });
});
