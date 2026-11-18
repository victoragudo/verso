import { memo } from 'react';
import { StatusBadge } from '@/components/verso/ui';
import { msg, t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { Capability } from '@/types';
import { RiskBadge } from './risk-badge';

export type OverrideState = 'inherit' | 'grant' | 'deny';

const OPTIONS: { value: OverrideState; label: string }[] = [
    { value: 'inherit', label: msg('From roles') },
    { value: 'grant', label: msg('Grant') },
    { value: 'deny', label: msg('Deny') },
];

type Props = {
    capability: Capability;
    value: OverrideState;
    saved: OverrideState;
    grantable: boolean;
    allowed: boolean;
    hadIt: boolean;
    onChange: (key: string, value: OverrideState) => void;
};

// The options the viewer may pick: giving something back needs the capability, taking it away does not.
function disabledOption(
    option: OverrideState,
    saved: OverrideState,
    grantable: boolean,
): boolean {
    if (grantable || option === 'deny') {
        return false;
    }

    return option === 'grant' ? saved !== 'grant' : saved === 'deny';
}

// One capability on the user access page: "From roles", "Grant" or "Deny", and the result.
export const OverrideControl = memo(function OverrideControl({
    capability,
    value,
    saved,
    grantable,
    allowed,
    hadIt,
    onChange,
}: Props) {
    const name = `override-${capability.key}`;

    return (
        <li className="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-2.5 sm:px-5">
            <div className="min-w-0 flex-1 basis-56">
                <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span className="text-sm font-medium text-foreground">
                        {capability.label}
                    </span>
                    <RiskBadge risk={capability.risk} />
                </div>
                {capability.description && (
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        {capability.description}
                    </p>
                )}
            </div>
            <fieldset className="flex shrink-0 rounded-md border bg-muted/50 p-0.5">
                <legend className="sr-only">{capability.label}</legend>
                {OPTIONS.map((option) => {
                    const disabled = disabledOption(
                        option.value,
                        saved,
                        grantable,
                    );

                    return (
                        <label
                            key={option.value}
                            className={cn(
                                'relative inline-flex min-h-10 cursor-pointer items-center rounded px-2.5 text-xs transition-colors hover:bg-accent/60 font-medium whitespace-nowrap text-muted-foreground has-[:checked]:bg-card has-[:checked]:text-foreground has-[:checked]:shadow-xs has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-50 has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-ring/50 md:min-h-8',
                                option.value === 'grant' &&
                                    'has-[:checked]:text-success-text',
                                option.value === 'deny' &&
                                    'has-[:checked]:text-danger-text',
                            )}
                        >
                            <input
                                type="radio"
                                name={name}
                                value={option.value}
                                checked={value === option.value}
                                disabled={disabled}
                                onChange={() =>
                                    onChange(capability.key, option.value)
                                }
                                className="sr-only"
                            />
                            {t(option.label)}
                        </label>
                    );
                })}
            </fieldset>
            <span className="min-w-28 shrink-0 text-end">
                {allowed ? (
                    <StatusBadge tone="success">
                        {hadIt ? t('Allowed') : t('Will be allowed')}
                    </StatusBadge>
                ) : (
                    <StatusBadge tone={hadIt ? 'danger' : 'neutral'}>
                        {hadIt ? t('Will be taken') : t('Not allowed')}
                    </StatusBadge>
                )}
            </span>
        </li>
    );
});
