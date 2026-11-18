import { useMemo } from 'react';
import { t } from '@/lib/i18n';
import { passwordStrength } from '@/lib/password-strength';
import { cn } from '@/lib/utils';

type Props = {
    id: string;
    password: string;
    minLength: number;
    context?: string[];
};

const SEGMENTS = 4;

const TONES = [
    'bg-red-600',
    'bg-red-600',
    'bg-amber-500',
    'bg-emerald-600',
    'bg-emerald-600',
];

// A four part bar with a label and tips. Screen readers hear the label when it changes.
export function PasswordStrengthMeter({
    id,
    password,
    minLength,
    context = [],
}: Props) {
    const strength = useMemo(
        () => passwordStrength(password, minLength, context),
        [password, minLength, context],
    );

    return (
        <div id={id} className="grid gap-1.5" data-score={strength.score}>
            <div className="flex gap-1" aria-hidden="true">
                {Array.from({ length: SEGMENTS }, (_, index) => (
                    <span
                        key={index}
                        data-testid="strength-segment"
                        className={cn(
                            'h-1.5 flex-1 rounded-full bg-zinc-200 transition-colors motion-reduce:transition-none dark:bg-zinc-800',
                            password !== '' &&
                                index < Math.max(1, strength.score) &&
                                TONES[strength.score],
                        )}
                    />
                ))}
            </div>
            <p className="text-xs text-muted-foreground" aria-live="polite">
                {password === '' ? (
                    t(
                        'At least {count, plural, one {# character} other {# characters}}, with letters and numbers.',
                        { count: minLength },
                    )
                ) : (
                    <>
                        <span className="font-medium text-foreground">
                            {t('Strength: {label}.', {
                                label: t(strength.label),
                            })}
                        </span>{' '}
                        {strength.hints[0] ??
                            t('Nice, this is a strong password.')}
                    </>
                )}
            </p>
        </div>
    );
}
