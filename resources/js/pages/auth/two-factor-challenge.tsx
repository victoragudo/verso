import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';
import { t, Trans } from '@/lib/i18n';
import { store } from '@/routes/two-factor/login';

// Asks for a two factor code or a recovery code after the password.
export default function TwoFactorChallenge() {
    const [showRecoveryInput, setShowRecoveryInput] = useState<boolean>(false);
    const [code, setCode] = useState<string>('');

    const authConfigContent = useMemo<{
        title: string;
        description: string;
        toggleText: string;
    }>(() => {
        if (showRecoveryInput) {
            return {
                title: t('Recovery code'),
                description: t(
                    'Please confirm access to your account by entering one of your emergency recovery codes.',
                ),
                toggleText: t(
                    'or you can <toggle>log in using an authentication code</toggle>',
                ),
            };
        }

        return {
            title: t('Authentication code'),
            description: t(
                'Enter the authentication code provided by your authenticator application.',
            ),
            toggleText: t(
                'or you can <toggle>log in using a recovery code</toggle>',
            ),
        };
    }, [showRecoveryInput]);

    setLayoutProps({
        title: authConfigContent.title,
        description: authConfigContent.description,
    });

    const toggleRecoveryMode = (clearErrors: () => void): void => {
        setShowRecoveryInput(!showRecoveryInput);
        clearErrors();
        setCode('');
    };

    return (
        <>
            <Head title={t('Two-factor authentication')} />

            <div className="space-y-6">
                <Form
                    {...store.form()}
                    className="space-y-4"
                    resetOnError
                    resetOnSuccess={!showRecoveryInput}
                >
                    {({ errors, processing, clearErrors }) => (
                        <>
                            {showRecoveryInput ? (
                                <div className="grid gap-2">
                                    <Label htmlFor="recovery_code">
                                        {t('Recovery code')}
                                    </Label>
                                    <Input
                                        id="recovery_code"
                                        name="recovery_code"
                                        type="text"
                                        autoComplete="off"
                                        autoCapitalize="none"
                                        spellCheck={false}
                                        autoFocus={showRecoveryInput}
                                        required
                                        className="h-10"
                                    />
                                    <InputError
                                        message={errors.recovery_code}
                                    />
                                </div>
                            ) : (
                                <div className="flex flex-col items-center justify-center space-y-3 text-center">
                                    <div className="flex w-full items-center justify-center">
                                        <InputOTP
                                            name="code"
                                            maxLength={OTP_MAX_LENGTH}
                                            value={code}
                                            onChange={(value) => setCode(value)}
                                            disabled={processing}
                                            pattern={REGEXP_ONLY_DIGITS}
                                            autoComplete="one-time-code"
                                            inputMode="numeric"
                                            aria-label={t(
                                                'Authentication code',
                                            )}
                                            autoFocus
                                        >
                                            <InputOTPGroup>
                                                {Array.from(
                                                    { length: OTP_MAX_LENGTH },
                                                    (_, index) => (
                                                        <InputOTPSlot
                                                            key={index}
                                                            index={index}
                                                        />
                                                    ),
                                                )}
                                            </InputOTPGroup>
                                        </InputOTP>
                                    </div>
                                    <InputError message={errors.code} />
                                </div>
                            )}

                            <Button
                                type="submit"
                                className="h-10 w-full md:h-10"
                                disabled={processing}
                            >
                                {processing && <Spinner aria-hidden="true" />}
                                {t('Continue')}
                            </Button>

                            <div className="text-center text-sm text-muted-foreground">
                                <Trans
                                    text={authConfigContent.toggleText}
                                    components={{
                                        toggle: (text) => (
                                            <button
                                                type="button"
                                                className="inline-flex min-h-10 cursor-pointer items-center text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                                onClick={() =>
                                                    toggleRecoveryMode(
                                                        clearErrors,
                                                    )
                                                }
                                            >
                                                {text}
                                            </button>
                                        ),
                                    }}
                                />
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
