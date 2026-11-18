import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { focusFirstError } from '@/lib/form-focus';
import { t, Trans } from '@/lib/i18n';
import { login } from '@/routes';
import { store } from '@/routes/register';

type Props = {
    passwordRules: string;
};

// The sign up form for a new account.
export default function Register({ passwordRules }: Props) {
    return (
        <>
            <Head title={t('Register')} />
            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                onError={focusFirstError}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">{t('Name')}</Label>
                                <Input
                                    id="name"
                                    type="text"
                                    required
                                    autoFocus
                                    autoComplete="name"
                                    maxLength={255}
                                    enterKeyHint="next"
                                    name="name"
                                    placeholder={t('Full name')}
                                    className="h-10"
                                />
                                <InputError
                                    message={errors.name}
                                    className="mt-2"
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    {t('Email address')}
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    required
                                    autoComplete="username"
                                    spellCheck={false}
                                    maxLength={255}
                                    enterKeyHint="next"
                                    name="email"
                                    placeholder="you@example.com"
                                    className="h-10"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">
                                    {t('Password')}
                                </Label>
                                <PasswordInput
                                    id="password"
                                    required
                                    autoComplete="new-password"
                                    enterKeyHint="next"
                                    name="password"
                                    className="h-10"
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
                                    required
                                    autoComplete="new-password"
                                    enterKeyHint="done"
                                    name="password_confirmation"
                                    className="h-10"
                                    passwordrules={passwordRules}
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <Button
                                type="submit"
                                className="mt-2 h-10 w-full md:h-10"
                                disabled={processing}
                                data-test="register-user-button"
                            >
                                {processing && <Spinner aria-hidden="true" />}
                                {t('Create account')}
                            </Button>
                        </div>

                        <div className="text-center text-sm text-muted-foreground">
                            <Trans
                                text={t(
                                    'Already have an account? <link>Log in</link>',
                                )}
                                components={{
                                    link: (text) => (
                                        <TextLink href={login()}>
                                            {text}
                                        </TextLink>
                                    ),
                                }}
                            />
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

Register.layout = () => ({
    title: t('Create an account'),
    description: t('Enter your details below to create your account'),
});
