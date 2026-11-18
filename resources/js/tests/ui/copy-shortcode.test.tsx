import { act, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { CopyShortcode } from '@/components/verso/ui';

function mockClipboard(writeText: (text: string) => Promise<void>) {
    Object.defineProperty(navigator, 'clipboard', {
        value: { writeText },
        configurable: true,
    });
}

describe('CopyShortcode', () => {
    afterEach(() => {
        vi.useRealTimers();
    });

    it('copies the shortcode and says so in a status message', async () => {
        const user = userEvent.setup();
        const writeText = vi.fn().mockResolvedValue(undefined);
        render(
            <CopyShortcode
                value='[form slug="contact"]'
                label="Copy the shortcode of Contact"
            />,
        );
        mockClipboard(writeText);

        await user.click(
            screen.getByRole('button', {
                name: 'Copy the shortcode of Contact',
            }),
        );

        expect(writeText).toHaveBeenCalledWith('[form slug="contact"]');
        expect(screen.getByRole('status')).toHaveTextContent('Copied');
    });

    it('selects the text when the clipboard is denied', async () => {
        const user = userEvent.setup();
        render(<CopyShortcode value='[shop-product product="linen-apron"]' />);
        mockClipboard(() => Promise.reject(new DOMException('Denied')));

        await user.click(
            screen.getByRole('button', { name: 'Copy shortcode' }),
        );

        const field = screen.getByLabelText<HTMLInputElement>('Shortcode');
        expect(screen.getByRole('status')).toHaveTextContent('Copy blocked');
        expect(field).toHaveFocus();
        expect(field.selectionStart).toBe(0);
        expect(field.selectionEnd).toBe(field.value.length);
    });

    it('clears the message after a while', async () => {
        vi.useFakeTimers({ shouldAdvanceTime: true });
        const user = userEvent.setup({
            advanceTimers: vi.advanceTimersByTime,
        });
        render(<CopyShortcode value="[newsletter]" messageMs={500} />);
        mockClipboard(() => Promise.resolve());

        await user.click(
            screen.getByRole('button', { name: 'Copy shortcode' }),
        );
        expect(screen.getByRole('status')).toHaveTextContent('Copied');

        act(() => {
            vi.advanceTimersByTime(600);
        });
        expect(screen.getByRole('status')).toHaveTextContent('');
    });

    it('shows the Blade snippet in a disclosure', () => {
        render(
            <CopyShortcode
                value='[form slug="contact"]'
                blade={
                    '<x-verso-embed name="form" :attrs="[\'slug\' => \'contact\']" />'
                }
            />,
        );

        expect(
            screen.getByText('Use it in a theme view').closest('details'),
        ).not.toHaveAttribute('open');
    });
});
