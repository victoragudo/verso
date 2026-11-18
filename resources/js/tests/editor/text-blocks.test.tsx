import type { Editor } from '@tiptap/react';
import { act, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import {
    HeadingBlockFields,
    ListBlockFields,
    ParagraphBlockFields,
    QuoteBlockFields,
    switchListTag,
} from '@/components/verso/editor/text-blocks';
import type { Block } from '@/types';

// Renders a text block with focus and returns the Tiptap editor it uses.
async function renderTextBlock(
    Fields: typeof ParagraphBlockFields,
    block: Block,
) {
    const onChange = vi.fn<(block: Block) => void>();
    let editor: Editor | null = null;

    render(
        <Fields
            block={block}
            onChange={onChange}
            autoFocus
            onFocus={(current) => {
                editor = current;
            }}
        />,
    );

    await waitFor(() => expect(editor).not.toBeNull());

    return { onChange, editor: editor as unknown as Editor };
}

const paragraph: Block = {
    id: 'b_intro01',
    type: 'paragraph',
    attrs: {},
    content: '<p>Our bakery opens at <strong>seven</strong>.</p>',
    children: [],
};

describe('switchListTag', () => {
    it('turns a bulleted list into a numbered list', () => {
        expect(
            switchListTag(
                '<ul><li><p>Flour</p></li><li><p>Water</p></li></ul>',
                true,
            ),
        ).toBe('<ol><li><p>Flour</p></li><li><p>Water</p></li></ol>');
    });

    it('turns a numbered list into a bulleted list', () => {
        expect(switchListTag('<ol><li><p>Knead</p></li></ol>', false)).toBe(
            '<ul><li><p>Knead</p></li></ul>',
        );
    });

    it('drops attributes on the outer tag', () => {
        expect(
            switchListTag('<ol start="3"><li><p>Bake</p></li></ol>', false),
        ).toBe('<ul><li><p>Bake</p></li></ul>');
    });

    it('leaves nested lists alone', () => {
        const html =
            '<ul><li><p>Bread</p><ol><li><p>Rye</p></li></ol></li></ul>';

        expect(switchListTag(html, true)).toBe(
            '<ol><li><p>Bread</p><ol><li><p>Rye</p></li></ol></li></ol>',
        );
    });

    it('keeps the tag when it already matches', () => {
        expect(switchListTag('<ul><li><p>Salt</p></li></ul>', false)).toBe(
            '<ul><li><p>Salt</p></li></ul>',
        );
    });

    it('returns an empty string unchanged', () => {
        expect(switchListTag('', true)).toBe('');
    });
});

describe('ParagraphBlockFields', () => {
    it('shows the stored HTML as editable text', async () => {
        await renderTextBlock(ParagraphBlockFields, paragraph);

        const box = screen.getByRole('textbox', { name: 'Paragraph' });

        expect(box).toHaveTextContent('Our bakery opens at seven.');
        expect(box.querySelector('strong')).toHaveTextContent('seven');
    });

    it('saves the text wrapped in one p tag', async () => {
        const { editor, onChange } = await renderTextBlock(
            ParagraphBlockFields,
            paragraph,
        );

        act(() => {
            editor.commands.setContent(
                '<p>Fresh <em>croissants</em> every morning</p>',
                { emitUpdate: true },
            );
        });

        expect(onChange).toHaveBeenLastCalledWith({
            ...paragraph,
            content: '<p>Fresh <em>croissants</em> every morning</p>',
        });
    });

    it('saves an empty paragraph when the text is removed', async () => {
        const { editor, onChange } = await renderTextBlock(
            ParagraphBlockFields,
            paragraph,
        );

        act(() => {
            editor.commands.clearContent(true);
        });

        expect(onChange).toHaveBeenLastCalledWith({
            ...paragraph,
            content: '<p></p>',
        });
    });

    it('keeps links in the saved HTML without target attributes', async () => {
        const { editor, onChange } = await renderTextBlock(
            ParagraphBlockFields,
            paragraph,
        );

        act(() => {
            editor.commands.setContent(
                '<p>Read <a href="https://example.org/menu">our menu</a></p>',
                { emitUpdate: true },
            );
        });

        expect(onChange.mock.lastCall?.[0].content).toBe(
            '<p>Read <a href="https://example.org/menu">our menu</a></p>',
        );
    });

    it('drops block tags that a paragraph can not hold', async () => {
        const { editor, onChange } = await renderTextBlock(
            ParagraphBlockFields,
            paragraph,
        );

        act(() => {
            editor.commands.setContent('<h2>Opening hours</h2>', {
                emitUpdate: true,
            });
        });

        expect(onChange.mock.lastCall?.[0].content).toBe(
            '<p>Opening hours</p>',
        );
    });
});

describe('HeadingBlockFields', () => {
    const heading: Block = {
        id: 'b_head01',
        type: 'heading',
        attrs: { level: 3 },
        content: 'Opening hours',
        children: [],
    };

    it('labels the text box with the level', async () => {
        await renderTextBlock(HeadingBlockFields, heading);

        expect(
            screen.getByRole('textbox', { name: 'Heading level 3' }),
        ).toHaveTextContent('Opening hours');
    });

    it('defaults to level 2 when the level is missing', async () => {
        await renderTextBlock(HeadingBlockFields, { ...heading, attrs: {} });

        expect(
            screen.getByRole('textbox', { name: 'Heading level 2' }),
        ).toBeInTheDocument();
    });

    it('saves the inner HTML without a wrapper tag', async () => {
        const { editor, onChange } = await renderTextBlock(
            HeadingBlockFields,
            heading,
        );

        act(() => {
            editor.commands.setContent('<p>Weekend <em>hours</em></p>', {
                emitUpdate: true,
            });
        });

        expect(onChange).toHaveBeenLastCalledWith({
            ...heading,
            content: 'Weekend <em>hours</em>',
        });
    });
});

describe('ListBlockFields', () => {
    const list: Block = {
        id: 'b_list01',
        type: 'list',
        attrs: { ordered: false },
        content: '<ul><li><p>Flour</p></li><li><p>Water</p></li></ul>',
        children: [],
    };

    it('shows a bulleted list with its items', async () => {
        await renderTextBlock(ListBlockFields, list);

        const box = screen.getByRole('textbox', { name: 'Bulleted list' });

        expect(box.querySelectorAll('li')).toHaveLength(2);
        expect(
            screen.getByRole('button', { name: 'Bulleted list' }),
        ).toHaveAttribute('aria-pressed', 'true');
    });

    it('switches to a numbered list and rewrites the outer tag', async () => {
        const { onChange } = await renderTextBlock(ListBlockFields, list);

        await userEvent.click(
            screen.getByRole('button', { name: 'Numbered list' }),
        );

        expect(onChange).toHaveBeenCalledWith({
            ...list,
            attrs: { ordered: true },
            content: '<ol><li><p>Flour</p></li><li><p>Water</p></li></ol>',
        });
    });

    it('saves the full list HTML', async () => {
        const { editor, onChange } = await renderTextBlock(
            ListBlockFields,
            list,
        );

        act(() => {
            editor.commands.setContent(
                '<ul><li><p>Flour</p></li><li><p>Sea salt</p></li></ul>',
                { emitUpdate: true },
            );
        });

        expect(onChange.mock.lastCall?.[0].content).toBe(
            '<ul><li><p>Flour</p></li><li><p>Sea salt</p></li></ul>',
        );
    });
});

describe('QuoteBlockFields', () => {
    const quote: Block = {
        id: 'b_quote01',
        type: 'quote',
        attrs: { citation: 'Julia Child' },
        content:
            '<p>No one is born a great cook.</p><p>One learns by doing.</p>',
        children: [],
    };

    it('shows the paragraphs and the citation', async () => {
        await renderTextBlock(QuoteBlockFields, quote);

        expect(
            screen
                .getByRole('textbox', { name: 'Quote' })
                .querySelectorAll('p'),
        ).toHaveLength(2);
        expect(screen.getByRole('textbox', { name: 'Citation' })).toHaveValue(
            'Julia Child',
        );
    });

    it('saves several paragraphs as they are', async () => {
        const { editor, onChange } = await renderTextBlock(
            QuoteBlockFields,
            quote,
        );

        act(() => {
            editor.commands.setContent(
                '<p>Cooking is like love.</p><p>It should be entered into with abandon.</p>',
                { emitUpdate: true },
            );
        });

        expect(onChange.mock.lastCall?.[0].content).toBe(
            '<p>Cooking is like love.</p><p>It should be entered into with abandon.</p>',
        );
    });

    it('saves the citation in the attrs', async () => {
        const { onChange } = await renderTextBlock(QuoteBlockFields, {
            ...quote,
            attrs: { citation: '' },
        });

        await userEvent.type(
            screen.getByRole('textbox', { name: 'Citation' }),
            'J',
        );

        expect(onChange).toHaveBeenLastCalledWith({
            ...quote,
            attrs: { citation: 'J' },
        });
    });
});
