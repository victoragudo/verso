import { Form, Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { t } from '@/lib/i18n';

type Props = {
    name: string;
    email: string;
    submitUrl: string;
    passwordRules: string;
};

// Public page where an invited user chooses a password.
export default function AcceptInvitation({
    name,
    email,
    submitUrl,
    passwordRules,
}: Props) {
    return (
        <>
            <Head title={t('Accept invitation')} />

            <Form
                action={submitUrl}
                method="post"
                resetOnSuccess={['password', 'password_confirmation']}
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <p className="text-sm text-muted-foreground">
                            {t('Welcome, {name}.', { name })}
                        </p>
                        <div className="grid gap-2">
                            <Label htmlFor="email">{t('Email')}</Label>
                            <Input
                                id="email"
                                type="email"
                                value={email}
                                autoComplete="username"
                                readOnly
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="password">{t('Password')}</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                autoComplete="new-password"
                                required
                                enterKeyHint="next"
                                autoFocus
                                passwordrules={passwordRules}
                            />
                            <InputError message={errors.password} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                {t('Confirm password')}
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                autoComplete="new-password"
                                required
                                enterKeyHint="done"
                                passwordrules={passwordRules}
                            />
                        </div>
                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            {t('Activate account')}
                        </Button>
                    </div>
                )}
            </Form>
        </>
    );
}

AcceptInvitation.layout = (page: ReactNode) => (
    <AuthLayout
        title={t('Accept your invitation')}
        description={t('Choose a password to activate your account')}
    >
        {page}
    </AuthLayout>
);
