import { Form, Head } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

// Asks a new user to confirm their email address.
export default function VerifyEmail({ status }: { status?: string }) {
    return (
        <>
            <Head title={t('Email verification')} />

            {status === 'verification-link-sent' && (
                <p
                    role="status"
                    className="mb-6 rounded-md bg-success-soft px-3 py-2 text-sm text-success-text"
                >
                    {t(
                        'A new verification link has been sent to the email address you provided during registration.',
                    )}
                </p>
            )}

            <Form {...send.form()} className="space-y-6 text-center">
                {({ processing }) => (
                    <>
                        <Button
                            disabled={processing}
                            variant="secondary"
                            className="h-10 w-full md:h-10"
                        >
                            {processing && <Spinner />}
                            {t('Resend verification email')}
                        </Button>

                        <TextLink
                            href={logout()}
                            className="mx-auto inline-flex min-h-10 items-center text-sm"
                        >
                            {t('Log out')}
                        </TextLink>
                    </>
                )}
            </Form>
        </>
    );
}

VerifyEmail.layout = () => ({
    title: t('Email verification'),
    description: t(
        'Please verify your email address by clicking on the link we just emailed to you.',
    ),
});
