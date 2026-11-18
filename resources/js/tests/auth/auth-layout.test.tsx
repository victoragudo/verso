import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import PasswordInput from '@/components/password-input';
import AuthSimpleLayout from '@/layouts/auth/auth-simple-layout';
import { resetPageProps, setPageProps } from '@/tests/mocks/inertia';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

vi.mock('@/routes', () => ({ home: () => '/' }));

afterEach(() => resetPageProps());

describe('AuthSimpleLayout', () => {
    it('puts the form inside a main landmark', () => {
        render(
            <AuthSimpleLayout
                title="Log in to your account"
                description="Enter your email and password below to log in"
            >
                <form aria-label="Log in" />
            </AuthSimpleLayout>,
        );

        const main = screen.getByRole('main');

        expect(main).toContainElement(screen.getByRole('form'));
        expect(
            screen.getByRole('heading', {
                level: 1,
                name: 'Log in to your account',
            }),
        ).toBeInTheDocument();
    });

    it('names the logo link after the site home, not the page title', () => {
        setPageProps({ siteName: 'Harbor Lane Bakery' });

        render(
            <AuthSimpleLayout title="Forgot password" description="">
                <p>Enter your email to receive a reset link</p>
            </AuthSimpleLayout>,
        );

        const link = screen.getByRole('link', {
            name: 'Harbor Lane Bakery home',
        });

        expect(link).toHaveAttribute('href', '/');
    });

    it('falls back to a plain home name without a site name', () => {
        setPageProps({ siteName: '' });

        render(
            <AuthSimpleLayout title="Create an account" description="">
                <p>Sign up</p>
            </AuthSimpleLayout>,
        );

        expect(screen.getByRole('link', { name: 'Home' })).toBeInTheDocument();
    });
});

describe('PasswordInput', () => {
    it('lets keyboard users reach and use the show password button', async () => {
        const user = userEvent.setup();

        render(
            <label>
                Password
                <PasswordInput
                    name="password"
                    defaultValue="lisbon-coffee-42"
                />
            </label>,
        );

        const input = screen.getByLabelText('Password');

        await user.tab();
        expect(input).toHaveFocus();

        await user.tab();
        const toggle = screen.getByRole('button', { name: 'Show password' });
        expect(toggle).toHaveFocus();

        await user.keyboard('{Enter}');
        expect(input).toHaveAttribute('type', 'text');
        expect(
            screen.getByRole('button', { name: 'Hide password' }),
        ).toBeInTheDocument();
    });
});
