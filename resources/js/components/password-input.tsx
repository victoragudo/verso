import { ArrowBigUp, Eye, EyeOff } from 'lucide-react';
import type { ComponentProps, KeyboardEvent, Ref } from 'react';
import { useId, useState } from 'react';
import { Input } from '@/components/ui/input';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type Props = Omit<ComponentProps<'input'>, 'type'> & {
    ref?: Ref<HTMLInputElement>;
};

// A length limit from a passwordrules string like "minlength: 12; maxlength: 64;".
export function passwordRuleLength(
    rules: string | undefined,
    name: 'minlength' | 'maxlength',
): number | undefined {
    const match = new RegExp(`(?:^|;)\\s*${name}:\\s*(\\d+)`).exec(rules ?? '');

    return match ? Number(match[1]) : undefined;
}

// A password field with a show or hide button and a Caps Lock hint.
// It checks the length limits of its passwordrules before the form is sent.
export default function PasswordInput({
    className,
    ref,
    id,
    onKeyDown,
    onKeyUp,
    onBlur,
    minLength,
    maxLength,
    ...props
}: Props) {
    const generatedId = useId();
    const inputId = id ?? generatedId;
    const [showPassword, setShowPassword] = useState(false);
    const [capsLockOn, setCapsLockOn] = useState(false);

    const readCapsLock = (event: KeyboardEvent<HTMLInputElement>) => {
        setCapsLockOn(event.getModifierState?.('CapsLock') ?? false);
    };

    return (
        <div className="grid gap-1.5">
            <div className="relative">
                <Input
                    id={inputId}
                    type={showPassword ? 'text' : 'password'}
                    className={cn('pe-11', className)}
                    ref={ref}
                    minLength={
                        minLength ??
                        passwordRuleLength(props.passwordrules, 'minlength')
                    }
                    maxLength={
                        maxLength ??
                        passwordRuleLength(props.passwordrules, 'maxlength')
                    }
                    onKeyDown={(event) => {
                        readCapsLock(event);
                        onKeyDown?.(event);
                    }}
                    onKeyUp={(event) => {
                        readCapsLock(event);
                        onKeyUp?.(event);
                    }}
                    onBlur={(event) => {
                        setCapsLockOn(false);
                        onBlur?.(event);
                    }}
                    {...props}
                />
                <button
                    type="button"
                    onClick={() => setShowPassword((prev) => !prev)}
                    aria-controls={inputId}
                    aria-label={
                        showPassword ? t('Hide password') : t('Show password')
                    }
                    className="absolute inset-y-0 end-0 flex w-10 items-center justify-center rounded-e-md text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                >
                    {showPassword ? (
                        <EyeOff aria-hidden="true" className="size-4" />
                    ) : (
                        <Eye aria-hidden="true" className="size-4" />
                    )}
                </button>
            </div>
            <span role="status" className="flex empty:hidden">
                {capsLockOn && (
                    <span className="inline-flex min-w-0 items-center gap-1 rounded-sm bg-warning-soft px-1.5 py-0.5 text-xs font-medium text-warning-text">
                        <ArrowBigUp
                            aria-hidden="true"
                            className="size-3.5 shrink-0"
                        />
                        {t('Caps Lock is on')}
                    </span>
                )}
            </span>
        </div>
    );
}
