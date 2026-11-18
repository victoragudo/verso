import { Check } from 'lucide-react';
import { formatPercent, t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export type WizardStepItem = { key: string; label: string };

type Props = {
    steps: WizardStepItem[];
    current: string;
    done: string[];
};

// Shows where the user is in the wizard: a progress bar, and the step list on wide screens.
export function WizardStepper({ steps, current, done }: Props) {
    const index = Math.max(
        0,
        steps.findIndex((step) => step.key === current),
    );
    const percent = Math.round((index / Math.max(1, steps.length - 1)) * 100);

    return (
        <nav aria-label={t('Install progress')} className="grid gap-3">
            <div className="flex items-center justify-between text-xs text-muted-foreground">
                <span>
                    {t('Step {step} of {total}', {
                        step: index + 1,
                        total: steps.length,
                    })}
                    <span className="lg:hidden"> · {steps[index]?.label}</span>
                </span>
                <span className="tabular-nums">
                    {formatPercent(percent / 100)}
                </span>
            </div>
            <progress
                className="sr-only"
                max={steps.length}
                value={index + 1}
                aria-label={t('Install progress')}
            />
            <div
                className="h-1 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800"
                aria-hidden="true"
            >
                <div
                    className="h-full rounded-full bg-zinc-900 transition-[width] duration-300 motion-reduce:transition-none dark:bg-zinc-100"
                    style={{ width: `${percent}%` }}
                />
            </div>
            <ol className="hidden grid-cols-8 gap-2 lg:grid">
                {steps.map((step, position) => {
                    const isCurrent = step.key === current;
                    const isDone = done.includes(step.key) && !isCurrent;

                    return (
                        <li
                            key={step.key}
                            aria-current={isCurrent ? 'step' : undefined}
                            className="flex min-w-0 flex-col items-center gap-1.5 text-center"
                        >
                            <span
                                className={cn(
                                    'flex size-7 items-center justify-center rounded-full border text-xs font-medium tabular-nums',
                                    isCurrent &&
                                        'border-zinc-900 bg-zinc-900 text-white dark:border-zinc-100 dark:bg-zinc-100 dark:text-zinc-900',
                                    isDone &&
                                        'border-emerald-600 bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
                                    !isCurrent &&
                                        !isDone &&
                                        'border-zinc-200 bg-white text-zinc-500 dark:border-zinc-800 dark:bg-zinc-900',
                                )}
                            >
                                {isDone ? (
                                    <Check
                                        className="size-3.5"
                                        aria-hidden="true"
                                    />
                                ) : (
                                    position + 1
                                )}
                            </span>
                            <span
                                className={cn(
                                    'w-full truncate text-xs',
                                    isCurrent
                                        ? 'font-medium text-zinc-900 dark:text-zinc-100'
                                        : 'text-zinc-500',
                                )}
                            >
                                {step.label}
                                {isDone && (
                                    <span className="sr-only">
                                        {' '}
                                        {t('(done)')}
                                    </span>
                                )}
                            </span>
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
