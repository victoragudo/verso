import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

// A checkbox with a label and one line of help.
export function ToggleRow({
    checked,
    onChange,
    label,
    hint,
    disabled = false,
}: {
    checked: boolean;
    onChange: (checked: boolean) => void;
    label: string;
    hint: string;
    disabled?: boolean;
}) {
    return (
        <label
            className={cn(
                'flex cursor-pointer items-start gap-3 rounded-md border p-3 text-sm transition-colors hover:bg-muted/50',
                disabled && 'cursor-not-allowed opacity-60',
            )}
        >
            <Checkbox
                checked={checked}
                disabled={disabled}
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

// A label, a control, optional help and its error message.
export function Field({
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
            {hint && (
                <p
                    id={`${id}-hint`}
                    className="text-xs break-words text-muted-foreground"
                >
                    {hint}
                </p>
            )}
            <InputError message={error} />
        </div>
    );
}
