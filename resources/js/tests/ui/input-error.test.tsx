import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import InputError from '@/components/input-error';

function EmailField({ error, hint }: { error?: string; hint?: boolean }) {
    return (
        <div>
            <label htmlFor="invite-email">Email</label>
            <input
                id="invite-email"
                type="email"
                aria-describedby={hint ? 'invite-email-hint' : undefined}
            />
            {hint && <p id="invite-email-hint">We send the invite here.</p>}
            <InputError message={error} />
        </div>
    );
}

describe('InputError', () => {
    it('renders nothing and leaves the field alone without a message', () => {
        const { container } = render(<EmailField />);
        const input = screen.getByLabelText('Email');

        expect(container.querySelector('p')).toBeNull();
        expect(input).not.toHaveAttribute('aria-invalid');
        expect(input).not.toHaveAttribute('aria-describedby');
    });

    it('marks the field invalid and links the message to it', () => {
        render(<EmailField error="The email has already been taken." />);
        const input = screen.getByLabelText('Email');

        expect(input).toHaveAttribute('aria-invalid', 'true');
        expect(input).toHaveAccessibleDescription(
            'The email has already been taken.',
        );
    });

    it('keeps an existing description next to the error', () => {
        render(<EmailField hint error="Enter a valid email address." />);
        const input = screen.getByLabelText('Email');

        expect(input).toHaveAccessibleDescription(
            'We send the invite here. Enter a valid email address.',
        );
    });

    it('clears the link when the error goes away', () => {
        const { rerender } = render(
            <EmailField hint error="Enter a valid email address." />,
        );

        rerender(<EmailField hint />);
        const input = screen.getByLabelText('Email');

        expect(input).not.toHaveAttribute('aria-invalid');
        expect(input).toHaveAttribute('aria-describedby', 'invite-email-hint');
    });

    it('uses the control named by the label in the same group', () => {
        render(
            <div>
                <input aria-label="Search plans" />
                <label htmlFor="plan-price">Price</label>
                <input id="plan-price" inputMode="decimal" />
                <InputError message="The price must be at least 0." />
            </div>,
        );

        expect(screen.getByLabelText('Price')).toHaveAttribute(
            'aria-invalid',
            'true',
        );
        expect(screen.getByLabelText('Search plans')).not.toHaveAttribute(
            'aria-invalid',
        );
    });

    it('falls back to the first control when there is no label', () => {
        render(
            <div>
                <textarea aria-label="Comment" />
                <InputError message="The comment field is required." />
            </div>,
        );

        expect(screen.getByLabelText('Comment')).toHaveAccessibleDescription(
            'The comment field is required.',
        );
    });

    it('keeps a given id', () => {
        render(
            <div>
                <input aria-label="Slug" />
                <InputError id="slug-error" message="The slug is taken." />
            </div>,
        );

        expect(screen.getByText('The slug is taken.')).toHaveAttribute(
            'id',
            'slug-error',
        );
        expect(screen.getByLabelText('Slug')).toHaveAttribute(
            'aria-describedby',
            'slug-error',
        );
    });
});
