import { Check, Loader2, X } from 'lucide-react';
import { StatusBadge } from '@/components/verso/ui';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { CoreUpdateDetail, CoreUpdateStep } from '@/types';
import {
    progressPercent,
    stepLabel,
    stepTone,
    updateStatusTone,
} from './update-status';

type Props = {
    update: CoreUpdateDetail;
};

// Live view of one update run: a progress bar and every step with its message.
export function UpdateProgress({ update }: Props) {
    const percent =
        update.status === 'completed' ? 100 : progressPercent(update.steps);

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm font-medium text-foreground tabular-nums">
                    {t('Verso {from} to {to}', {
                        from: update.from_version,
                        to: update.to_version,
                    })}
                </p>
                <StatusBadge tone={updateStatusTone(update.status)}>
                    {update.status_label}
                </StatusBadge>
            </div>
            <div
                role="progressbar"
                aria-label={t('Update progress')}
                aria-valuemin={0}
                aria-valuemax={100}
                aria-valuenow={percent}
                className="h-2 overflow-hidden rounded-full bg-muted"
            >
                <div
                    className={cn(
                        'h-full rounded-full transition-[width] duration-500 motion-reduce:transition-none',
                        update.status === 'failed' ||
                            update.status === 'rolled_back'
                            ? 'bg-warning-text'
                            : 'bg-brand',
                    )}
                    style={{ width: `${percent}%` }}
                />
            </div>
            {update.error && (
                <p className="rounded-md bg-danger-soft px-3 py-2 text-sm break-words text-danger-text">
                    {update.error}
                </p>
            )}
            <ol className="divide-y rounded-lg border">
                {update.steps.map((step) => (
                    <StepRow key={step.key} step={step} />
                ))}
            </ol>
        </div>
    );
}

function StepRow({ step }: { step: CoreUpdateStep }) {
    return (
        <li className="flex items-start gap-3 px-3 py-2.5 sm:px-4">
            <StepMarker step={step} />
            <div className="min-w-0 flex-1">
                <p
                    className={cn(
                        'text-sm',
                        step.status === 'pending'
                            ? 'text-muted-foreground'
                            : 'font-medium text-foreground',
                    )}
                >
                    {step.label}
                </p>
                {step.message && (
                    <p className="mt-0.5 text-xs break-words text-muted-foreground">
                        {step.message}
                    </p>
                )}
            </div>
            {step.status !== 'pending' && (
                <StatusBadge tone={stepTone(step.status)}>
                    {stepLabel(step.status)}
                </StatusBadge>
            )}
        </li>
    );
}

function StepMarker({ step }: { step: CoreUpdateStep }) {
    const base =
        'mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full';

    if (step.status === 'running') {
        return (
            <span className={cn(base, 'text-info-text')}>
                <Loader2 className="size-4 animate-spin" aria-hidden="true" />
            </span>
        );
    }

    if (step.status === 'done') {
        return (
            <span className={cn(base, 'bg-success-soft text-success-text')}>
                <Check className="size-3.5" aria-hidden="true" />
            </span>
        );
    }

    if (step.status === 'failed') {
        return (
            <span className={cn(base, 'bg-danger-soft text-danger-text')}>
                <X className="size-3.5" aria-hidden="true" />
            </span>
        );
    }

    return (
        <span
            className={cn(
                base,
                'border',
                step.status === 'warning' && 'border-warning-text',
            )}
            aria-hidden="true"
        />
    );
}
