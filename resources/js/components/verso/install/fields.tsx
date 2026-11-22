import { CheckCircle2, CircleExclamationPoint } from 'lucide-react';
import type { ReactElement, ReactNode } from 'react';
import { cloneElement, useId } from 'react';
import PasswordInput from '@/components/password-input';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type FieldProps = {
    label: string;
    error?: string;
    hint?: ReactNode;
    optional?: boolean;
    className?: string;
    children: ReactElement<Record<string, unknown>>;
};

// A label, the control, a hint and the server error, wired with ids for screen readers.
export function Field({
    label,
    error,
    hint,
    optional,
    className,
    children,
}: FieldProps) {
    const id = useId();
    const hintId = `${id}-hint`;
    const errorId = `${id}-error`;
    const describedBy =
        [hint ? hintId : null, error ? errorId : null]
            .filter(Boolean)
            .join(' ') || undefined;

    return (
        <div className={cn('grid content-start gap-1.5', className)}>
            <Label htmlFor={id}>
                {label}
                {optional && (
                    <span className="font-normal text-muted-foreground">
                        {' '}
                        {t('(optional)')}
                    </span>
                )}
            </Label>
            {cloneElement(children, {
                id,
                'aria-describedby': describedBy,
                'aria-invalid': error ? true : undefined,
            })}
            {hint && (
                <p id={hintId} className="text-xs text-muted-foreground">
                    {hint}
                </p>
            )}
            {error && (
                <p
                    id={errorId}
                    className="text-sm text-red-700 dark:text-red-400"
                >
                    {error}
                </p>
            )}
        </div>
    );
}

type SecretInputProps = {
    id?: string;
    name: string;
    value: string;
    saved: boolean;
    autoComplete: string;
    onChange: (value: string) => void;
    'aria-describedby'?: string;
    'aria-invalid'?: boolean;
};

// A password field. A saved secret is never sent back: an empty field keeps it.
export function SecretInput({ saved, onChange, ...props }: SecretInputProps) {
    return (
        <PasswordInput
            {...props}
            placeholder={
                saved ? t('Saved. Leave empty to keep it.') : undefined
            }
            onChange={(event) => onChange(event.target.value)}
            spellCheck={false}
            autoCapitalize="none"
        />
    );
}

export type ChoiceOption = {
    value: string;
    label: string;
    description?: ReactNode;
    badge?: string;
    disabled?: boolean;
};

type ChoiceCardsProps = {
    legend: string;
    name: string;
    value: string;
    options: ChoiceOption[];
    onChange: (value: string) => void;
    columns?: 2 | 3 | 4;
    error?: string;
};

// Radio buttons drawn as cards. Arrow keys move between them like normal radios.
export function ChoiceCards({
    legend,
    name,
    value,
    options,
    onChange,
    columns = 2,
    error,
}: ChoiceCardsProps) {
    return (
        <fieldset className="grid gap-2">
            <legend className="mb-1.5 text-sm font-medium">{legend}</legend>
            <div
                className={cn(
                    'grid gap-2 sm:grid-cols-2',
                    columns === 3 && 'lg:grid-cols-3',
                    columns === 4 && 'lg:grid-cols-4',
                )}
            >
                {options.map((option) => (
                    <label
                        key={option.value}
                        className={cn(
                            'relative flex min-h-11 cursor-pointer flex-col gap-1 rounded-lg border bg-white p-3 text-sm shadow-xs transition-colors has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-ring/50 dark:bg-zinc-900',
                            value === option.value
                                ? 'border-zinc-900 ring-1 ring-zinc-900 dark:border-zinc-100 dark:ring-zinc-100'
                                : 'border-zinc-200 hover:border-zinc-300 dark:border-zinc-800',
                            option.disabled &&
                                'cursor-not-allowed opacity-60 hover:border-zinc-200',
                        )}
                    >
                        <input
                            type="radio"
                            name={name}
                            value={option.value}
                            checked={value === option.value}
                            disabled={option.disabled}
                            onChange={() => onChange(option.value)}
                            className="sr-only"
                        />
                        <span className="flex items-center justify-between gap-2 font-medium text-zinc-900 dark:text-zinc-100">
                            {option.label}
                            {option.badge && (
                                <span className="rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                                    {option.badge}
                                </span>
                            )}
                        </span>
                        {option.description && (
                            <span className="text-xs text-muted-foreground">
                                {option.description}
                            </span>
                        )}
                    </label>
                ))}
            </div>
            {error && (
                <p className="text-sm text-red-700 dark:text-red-400">
                    {error}
                </p>
            )}
        </fieldset>
    );
}

type ResultProps = { ok: boolean; title?: string; children: ReactNode };

// The answer of a test button, read out by screen readers.
export function ResultBox({ ok, title, children }: ResultProps) {
    const Icon = ok ? CheckCircle2 : CircleExclamationPoint;

    return (
        <div
            role="status"
            className={cn(
                'flex gap-2.5 rounded-lg border p-3 text-sm',
                ok
                    ? 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-100'
                    : 'border-red-200 bg-red-50 text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-100',
            )}
        >
            <Icon className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <div className="grid min-w-0 gap-1 break-words">
                {title && <p className="font-medium">{title}</p>}
                {children}
            </div>
        </div>
    );
}

// A titled group of fields inside a step.
export function FormSection({
    title,
    description,
    children,
}: {
    title: string;
    description?: string;
    children: ReactNode;
}) {
    return (
        <section className="grid gap-4 border-t border-zinc-200 pt-5 first:border-t-0 first:pt-0 dark:border-zinc-800">
            <div>
                <h3 className="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                    {title}
                </h3>
                {description && (
                    <p className="mt-0.5 text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {children}
        </section>
    );
}
