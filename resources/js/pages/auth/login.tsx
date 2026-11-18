import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { focusFirstError } from '@/lib/form-focus';
import { t, Trans } from '@/lib/i18n';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

const FIELD_CLASS = 'grid grid-rows-[auto_auto_minmax(1.25rem,auto)] gap-2';

// The admin sign in form with email and password, or a passkey.
export default function Login({ status, canResetPassword }: Props) {
    return (
        <>
            <Head title={t('Log in')} />

            {status && (
                <p
                    role="status"
                    className="mb-6 rounded-md bg-success-soft px-3 py-2 text-sm text-success-text"
                >
                    {status}
                </p>
            )}

            <PasskeyVerify />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                onError={focusFirstError}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-3">
                            <div className={FIELD_CLASS}>
                                <Label htmlFor="email">
                                    {t('Email address')}
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    autoComplete="username"
                                    spellCheck={false}
                                    enterKeyHint="next"
                                    placeholder="you@example.com"
                                    className="h-10"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className={FIELD_CLASS}>
                                <Label htmlFor="password">
                                    {t('Password')}
                                </Label>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    autoComplete="current-password"
                                    enterKeyHint="go"
                                    className="h-10"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex min-h-10 items-center justify-between gap-4">
                                <div className="flex items-center gap-2.5">
                                    <Checkbox id="remember" name="remember" />
                                    <Label
                                        htmlFor="remember"
                                        className="font-normal"
                                    >
                                        {t('Remember me')}
                                    </Label>
                                </div>
                                {canResetPassword && (
                                    <TextLink
                                        href={request()}
                                        className="inline-flex min-h-10 items-center text-sm"
                                    >
                                        {t('Forgot password?')}
                                    </TextLink>
                                )}
                            </div>
                        </div>

                        <Button
                            type="submit"
                            className="h-10 w-full md:h-10"
                            disabled={processing}
                            aria-busy={processing}
                            data-test="login-button"
                        >
                            {processing && <Spinner aria-hidden="true" />}
                            {processing ? t('Logging in…') : t('Log in')}
                        </Button>

                        <p className="text-center text-sm text-muted-foreground">
                            <Trans
                                text={t(
                                    "Don't have an account? <link>Sign up</link>",
                                )}
                                components={{
                                    link: (text) => (
                                        <TextLink href={register()}>
                                            {text}
                                        </TextLink>
                                    ),
                                }}
                            />
                        </p>
                    </>
                )}
            </Form>
        </>
    );
}

Login.layout = () => ({
    title: t('Log in to Verso'),
    description: t('Use your email and password, or a passkey.'),
});
