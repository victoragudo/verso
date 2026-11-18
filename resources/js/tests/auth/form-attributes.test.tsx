import { render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import PasswordInput, { passwordRuleLength } from '@/components/password-input';
import { Input } from '@/components/ui/input';
import { focusFirstError } from '@/lib/form-focus';
import Register from '@/pages/auth/register';
import AcceptInvitation from '@/pages/users/accept-invitation';

vi.mock('@inertiajs/react', async () => {
    const { inertiaMock } = await import('@/tests/mocks/inertia');

    // A plain form that hands empty state to the render function.
    const Form = ({
        children,
    }: {
        children:
            | ReactNode
            | ((state: {
                  processing: boolean;
                  errors: Record<string, string>;
              }) => ReactNode);
    }) => (
        <form>
            {typeof children === 'function'
                ? children({ processing: false, errors: {} })
                : children}
        </form>
    );

    return { ...inertiaMock, Form };
});

const PRODUCTION_RULES =
    'minlength: 12; required: lower; required: upper; required: digit; required: special;';

afterEach(() => {
    document.body.innerHTML = '';
});

describe('password rules', () => {
    it('reads the length limits of a passwordrules string', () => {
        expect(passwordRuleLength(PRODUCTION_RULES, 'minlength')).toBe(12);
        expect(passwordRuleLength(PRODUCTION_RULES, 'maxlength')).toBe(
            undefined,
        );
        expect(
            passwordRuleLength('minlength: 8; maxlength: 64;', 'maxlength'),
        ).toBe(64);
        expect(passwordRuleLength(undefined, 'minlength')).toBe(undefined);
    });

    it('gives the password field the same limits as the server', () => {
        render(
            <>
                <label htmlFor="new-password">New password</label>
                <PasswordInput
                    id="new-password"
                    autoComplete="new-password"
                    passwordrules="minlength: 12; maxlength: 64;"
                />
            </>,
        );

        const input = screen.getByLabelText('New password');

        expect(input).toHaveAttribute('minlength', '12');
        expect(input).toHaveAttribute('maxlength', '64');
    });
});

describe('Register', () => {
    it('uses sign up autofill tokens and the server limits', () => {
        render(<Register passwordRules={PRODUCTION_RULES} />);

        const name = screen.getByLabelText('Name');
        const email = screen.getByLabelText('Email address');
        const password = screen.getByLabelText('Password');
        const confirmation = screen.getByLabelText('Confirm password');

        expect(name).toHaveAttribute('autocomplete', 'name');
        expect(name).toHaveAttribute('maxlength', '255');
        expect(email).toHaveAttribute('autocomplete', 'username');
        expect(email).toHaveAttribute('type', 'email');
        expect(email).toBeRequired();
        expect(password).toHaveAttribute('autocomplete', 'new-password');
        expect(password).toHaveAttribute('minlength', '12');
        expect(password).toHaveAttribute('enterkeyhint', 'next');
        expect(confirmation).toHaveAttribute('enterkeyhint', 'done');
    });
});

describe('AcceptInvitation', () => {
    it('ties the new password to the invited email for password managers', () => {
        render(
            <AcceptInvitation
                name="Lucía Romero"
                email="lucia@harborlane.es"
                submitUrl="/invitations/accept"
                passwordRules={PRODUCTION_RULES}
            />,
        );

        expect(screen.getByLabelText('Email')).toHaveAttribute(
            'autocomplete',
            'username',
        );
        expect(screen.getByLabelText('Password')).toBeRequired();
        expect(screen.getByLabelText('Confirm password')).toBeRequired();
    });
});

describe('Input', () => {
    it('shows a search key on the keyboard of search fields', () => {
        render(<Input type="search" aria-label="Search posts" />);

        expect(screen.getByLabelText('Search posts')).toHaveAttribute(
            'enterkeyhint',
            'search',
        );
    });

    it('keeps a given enter key hint', () => {
        render(
            <Input type="search" enterKeyHint="go" aria-label="Find order" />,
        );

        expect(screen.getByLabelText('Find order')).toHaveAttribute(
            'enterkeyhint',
            'go',
        );
    });
});

describe('focusFirstError', () => {
    it('moves focus to the first field with a server error', async () => {
        render(
            <form>
                <input id="name" aria-label="Name" />
                <input id="email" aria-label="Email" />
            </form>,
        );

        focusFirstError({ email: 'The email has already been taken.' });
        await new Promise((resolve) => requestAnimationFrame(resolve));

        expect(screen.getByLabelText('Email')).toHaveFocus();
    });

    it('falls back to the first invalid field when no id matches', async () => {
        render(
            <form>
                <input aria-label="Slug" />
                <input aria-label="Title" aria-invalid="true" />
            </form>,
        );

        focusFirstError({ 'meta.title': 'The title is too long.' });
        await new Promise((resolve) => requestAnimationFrame(resolve));

        expect(screen.getByLabelText('Title')).toHaveFocus();
    });
});
