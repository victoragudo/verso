import { fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import PasswordInput from '@/components/password-input';

function renderField() {
    render(
        <>
            <label htmlFor="password">Password</label>
            <PasswordInput
                id="password"
                name="password"
                autoComplete="current-password"
                defaultValue="harbour-light-1987"
            />
        </>,
    );

    return screen.getByLabelText('Password');
}

describe('PasswordInput visibility toggle', () => {
    it('starts hidden and keeps the autocomplete hint', () => {
        const input = renderField();

        expect(input).toHaveAttribute('type', 'password');
        expect(input).toHaveAttribute('autocomplete', 'current-password');
    });

    it('shows and hides the password with the same button', async () => {
        const user = userEvent.setup();
        const input = renderField();

        await user.click(screen.getByRole('button', { name: 'Show password' }));
        expect(input).toHaveAttribute('type', 'text');

        await user.click(screen.getByRole('button', { name: 'Hide password' }));
        expect(input).toHaveAttribute('type', 'password');
        expect(input).toHaveValue('harbour-light-1987');
    });

    it('points the button at the field it controls', () => {
        renderField();

        expect(
            screen.getByRole('button', { name: 'Show password' }),
        ).toHaveAttribute('aria-controls', 'password');
    });

    it('never submits the form', async () => {
        const user = userEvent.setup();
        const onSubmit = vi.fn((event: SubmitEvent) => event.preventDefault());

        render(
            <form
                onSubmit={(event) => onSubmit(event.nativeEvent as SubmitEvent)}
            >
                <PasswordInput aria-label="Password" name="password" />
            </form>,
        );

        await user.click(screen.getByRole('button', { name: 'Show password' }));

        expect(onSubmit).not.toHaveBeenCalled();
    });
});

describe('PasswordInput Caps Lock hint', () => {
    it('tells the user when Caps Lock is on and hides the hint on blur', () => {
        const input = renderField();

        fireEvent.keyDown(input, { key: 'A', modifierCapsLock: true });
        expect(screen.getByRole('status')).toHaveTextContent('Caps Lock is on');

        fireEvent.keyUp(input, { key: 'CapsLock', modifierCapsLock: false });
        expect(screen.getByRole('status')).toBeEmptyDOMElement();

        fireEvent.keyDown(input, { key: 'B', modifierCapsLock: true });
        fireEvent.blur(input);
        expect(screen.getByRole('status')).toBeEmptyDOMElement();
    });
});
