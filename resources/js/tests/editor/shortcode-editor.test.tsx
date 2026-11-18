import { act, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { BlockEditor } from '@/components/verso/editor/block-editor';
import type { Block } from '@/types';
import { formDefinition } from './shortcode-fixtures';

function Controlled({
    initial,
    onChange,
}: {
    initial: Block[];
    onChange: (blocks: Block[]) => void;
}) {
    const [blocks, setBlocks] = useState(initial);

    return (
        <BlockEditor
            value={blocks}
            pluginBlocks={[formDefinition]}
            onChange={(next) => {
                setBlocks(next);
                onChange(next);
            }}
        />
    );
}

function paragraph(html: string): Block {
    return {
        id: 'b_p1',
        type: 'paragraph',
        attrs: {},
        content: html,
        children: [],
    };
}

function paste(target: HTMLElement, text: string) {
    const event = new Event('paste', { bubbles: true, cancelable: true });
    Object.assign(event, {
        clipboardData: {
            getData: (type: string) => (type === 'text/plain' ? text : ''),
            types: ['text/plain'],
            items: [],
            files: [],
        },
    });
    act(() => {
        target.dispatchEvent(event);
    });
}

describe('shortcodes in the block editor', () => {
    it('turns a shortcode pasted alone into its block', async () => {
        const onChange = vi.fn<(blocks: Block[]) => void>();
        render(
            <Controlled initial={[paragraph('<p></p>')]} onChange={onChange} />,
        );
        const box = await screen.findByRole('textbox', { name: 'Paragraph' });

        paste(box, '[form slug="catering"]');

        await waitFor(() =>
            expect(onChange.mock.lastCall?.[0][0]).toMatchObject({
                id: 'b_p1',
                type: 'form',
                attrs: { form: 'catering' },
            }),
        );
    });

    it('shows inline shortcodes as chips and keeps them as text when saving', async () => {
        const onChange = vi.fn<(blocks: Block[]) => void>();
        render(
            <Controlled
                initial={[
                    paragraph(
                        '<p>Questions? [form slug="contact"] We reply fast.</p>',
                    ),
                ]}
                onChange={onChange}
            />,
        );

        const chip = await screen.findByRole('button', {
            name: /Form\s*shortcode, edit settings/,
        });
        expect(chip).toHaveAttribute('title', '[form slug="contact"]');

        await userEvent.click(chip);
        expect(
            await screen.findByText('[form slug="contact"]', {
                selector: 'code',
            }),
        ).toBeInTheDocument();
    });

    it('turns a pasted inline shortcode into a chip and serializes it as text', async () => {
        const onChange = vi.fn<(blocks: Block[]) => void>();
        render(
            <Controlled
                initial={[paragraph('<p>Order here</p>')]}
                onChange={onChange}
            />,
        );
        const box = await screen.findByRole('textbox', { name: 'Paragraph' });

        paste(box, 'Order here: [form slug="catering"] today');

        await waitFor(() =>
            expect(onChange.mock.lastCall?.[0][0].content).toContain(
                '[form slug="catering"]',
            ),
        );
        expect(onChange.mock.lastCall?.[0][0].content).not.toContain(
            'data-shortcode',
        );
        expect(
            screen.getAllByRole('button', { name: /shortcode, edit settings/ })
                .length,
        ).toBeGreaterThan(0);
    });
});
