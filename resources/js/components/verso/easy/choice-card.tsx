import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    name: string;
    value: string;
    label: string;
    description?: string;
    checked: boolean;
    disabled?: boolean;
    onSelect: (value: string) => void;
    children?: ReactNode;
    className?: string;
};

// A radio choice in a card: a drawing on top, then the radio with its label. Arrow keys move inside the group.
export function ChoiceCard({
    name,
    value,
    label,
    description,
    checked,
    disabled = false,
    onSelect,
    children,
    className,
}: Props) {
    const id = `${name}-${value}`;

    return (
        <label
            className={cn(
                'flex cursor-pointer flex-col gap-3 rounded-lg border bg-card p-3 shadow-xs transition-colors hover:border-zinc-300 has-checked:border-brand has-checked:ring-1 has-checked:ring-brand has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-brand has-disabled:cursor-not-allowed has-disabled:opacity-70 dark:hover:border-zinc-600',
                className,
            )}
        >
            {children}
            <span className="flex items-start gap-2.5">
                <input
                    type="radio"
                    name={name}
                    value={value}
                    checked={checked}
                    disabled={disabled}
                    onChange={() => onSelect(value)}
                    aria-labelledby={`${id}-label`}
                    aria-describedby={
                        description ? `${id}-description` : undefined
                    }
                    className="mt-0.5 size-4 shrink-0 accent-brand focus-visible:outline-none"
                />
                <span className="min-w-0">
                    <span
                        id={`${id}-label`}
                        className="block text-sm font-semibold text-foreground"
                    >
                        {label}
                    </span>
                    {description && (
                        <span
                            id={`${id}-description`}
                            className="mt-0.5 block text-xs text-muted-foreground"
                        >
                            {description}
                        </span>
                    )}
                </span>
            </span>
        </label>
    );
}
