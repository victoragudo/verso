import { describe, expect, it } from 'vitest';
import {
    attrsToShortcode,
    shortcodeToBlock,
    singleShortcode,
    splitShortcodes,
    unwrapShortcodeChips,
    wrapShortcodeChips,
} from '@/components/verso/editor/shortcodes';
import { formDefinition } from './shortcode-fixtures';

describe('shortcode tokenizer', () => {
    it('reads quoted, bare and flag attributes', () => {
        expect(
            singleShortcode(
                `[form slug="contact" title='Ask Marta' size=2 compact]`,
            ),
        ).toEqual({
            name: 'form',
            attrs: {
                slug: 'contact',
                title: 'Ask Marta',
                size: '2',
                compact: true,
            },
            raw: `[form slug="contact" title='Ask Marta' size=2 compact]`,
        });
    });

    it('keeps brackets inside quoted values', () => {
        expect(
            singleShortcode('[form title="Fresh [daily] bread"]')?.attrs,
        ).toEqual({ title: 'Fresh [daily] bread' });
    });

    it('splits text around shortcodes and leaves escapes and broken tags as text', () => {
        expect(
            splitShortcodes(
                'Write [form slug="a"] or [[form]] or [Lisbon] or [form x="',
            ).map((part) => part.kind),
        ).toEqual(['text', 'shortcode', 'text']);
    });

    it('is not a single shortcode when other text is around it', () => {
        expect(singleShortcode('Hi [form]')).toBeNull();
        expect(singleShortcode('  [form slug="contact"]\n')).not.toBeNull();
    });
});

describe('shortcode and block conversion', () => {
    it('builds a block with aliases renamed and defaults filled', () => {
        const block = shortcodeToBlock(
            singleShortcode('[form slug="catering" compact]')!,
            [formDefinition],
        );

        expect(block).toMatchObject({
            type: 'form',
            attrs: { form: 'catering', compact: true },
            children: [],
        });
    });

    it('gives no block for unknown names or attributes', () => {
        expect(
            shortcodeToBlock(singleShortcode('[gallery ids="1"]')!, [
                formDefinition,
            ]),
        ).toBeNull();
        expect(
            shortcodeToBlock(singleShortcode('[form color="red"]')!, [
                formDefinition,
            ]),
        ).toBeNull();
    });

    it('writes attrs back with the alias the author used', () => {
        const previous = singleShortcode('[form slug="contact"]');

        expect(
            attrsToShortcode(
                'form',
                { form: 'catering', title: 'Order "today"', compact: false },
                formDefinition,
                previous,
            ),
        ).toBe(`[form slug="catering" title='Order "today"']`);
    });
});

describe('chip HTML', () => {
    it('wraps known shortcodes in chip spans and leaves the rest', () => {
        expect(
            wrapShortcodeChips(
                '<p>Ask <strong>us</strong>: [form slug="contact"] [other]</p>',
                ['form'],
            ),
        ).toBe(
            '<p>Ask <strong>us</strong>: <span data-shortcode="">[form slug=&quot;contact&quot;]</span> [other]</p>',
        );
    });

    it('does not touch shortcodes inside tag attributes', () => {
        const html = '<a href="https://example.org/[form]">link</a>';

        expect(wrapShortcodeChips(html, ['form'])).toBe(html);
    });

    it('serializes chips back to plain shortcode text', () => {
        expect(
            unwrapShortcodeChips(
                '<p>Ask: <span data-shortcode="">[form slug="contact"]</span>.</p>',
            ),
        ).toBe('<p>Ask: [form slug="contact"].</p>');
    });
});
