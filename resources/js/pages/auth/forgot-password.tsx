import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { focusFirstError } from '@/lib/form-focus';
import { t, Trans } from '@/lib/i18n';
import { login } from '@/routes';
import { email } from '@/routes/password';

// Asks for an email address and sends a password reset link.
export default function ForgotPassword({ status }: { status?: string }) {
    return (
        <>
            <Head title={t('Forgot password')} />

            {status && (
                <p
                    role="status"
                    className="mb-6 rounded-md bg-success-soft px-3 py-2 text-sm text-success-text"
                >
                    {status}
                </p>
            )}

            <div className="space-y-6">
                <Form {...email.form()} onError={focusFirstError}>
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    {t('Email address')}
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoComplete="username"
                                    spellCheck={false}
                                    autoFocus
                                    placeholder="you@example.com"
                                    className="h-10"
                                />

                                <InputError message={errors.email} />
                            </div>

                            <div className="my-6 flex items-center justify-start">
                                <Button
                                    className="h-10 w-full md:h-10"
                                    disabled={processing}
                                    data-test="email-password-reset-link-button"
                                >
                                    {processing && (
                                        <Spinner aria-hidden="true" />
                                    )}
                                    {t('Email password reset link')}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>

                <div className="text-center text-sm text-muted-foreground">
                    <Trans
                        text={t('Or, return to <link>log in</link>')}
                        components={{
                            link: (text) => (
                                <TextLink href={login()}>{text}</TextLink>
                            ),
                        }}
                    />
                </div>
            </div>
        </>
    );
}

ForgotPassword.layout = () => ({
    title: t('Forgot password'),
    description: t('Enter your email to receive a password reset link'),
});
