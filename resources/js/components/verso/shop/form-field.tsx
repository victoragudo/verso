import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

// A label, a control, an optional hint and its error message.
export function FormField({
    label,
    id,
    error,
    hint,
    className,
    children,
}: {
    label: string;
    id: string;
    error?: string;
    hint?: ReactNode;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={cn('grid min-w-0 content-start gap-2', className)}>
            <Label htmlFor={id}>{label}</Label>
            {children}
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}

// A checkbox with a bold label and one line of help.
export function ToggleField({
    label,
    hint,
    checked,
    onChange,
    className,
}: {
    label: string;
    hint: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
    className?: string;
}) {
    return (
        <label
            className={cn(
                'flex cursor-pointer items-start gap-3 rounded-md border p-3 text-sm transition-colors hover:bg-muted/50',
                className,
            )}
        >
            <Checkbox
                checked={checked}
                onCheckedChange={(value) => onChange(value === true)}
                className="mt-0.5"
            />
            <span className="min-w-0">
                <span className="font-medium text-foreground">{label}</span>
                <span className="mt-0.5 block text-muted-foreground">
                    {hint}
                </span>
            </span>
        </label>
    );
}

// A text input with the currency code shown on the left.
export function MoneyPrefix({
    currency,
    children,
}: {
    currency: string;
    children: ReactNode;
}) {
    return (
        <div className="relative">
            <span className="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-xs font-medium text-muted-foreground">
                {currency}
            </span>
            {children}
        </div>
    );
}
