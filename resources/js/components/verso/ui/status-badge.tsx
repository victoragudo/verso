import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type StatusTone = 'success' | 'warning' | 'danger' | 'neutral' | 'info';

const TONES: Record<StatusTone, string> = {
    success: 'bg-success-soft text-success-text',
    warning: 'bg-warning-soft text-warning-text',
    danger: 'bg-danger-soft text-danger-text',
    neutral: 'bg-neutral-soft text-neutral-text',
    info: 'bg-info-soft text-info-text',
};

const DOTS: Record<StatusTone, string> = {
    success: 'bg-success-text',
    warning: 'bg-warning-text',
    danger: 'bg-danger-text',
    neutral: 'bg-neutral-text/60',
    info: 'bg-info-text',
};

const KNOWN_TONES: Record<string, StatusTone> = {
    published: 'success',
    approved: 'success',
    completed: 'success',
    active: 'success',
    scheduled: 'info',
    running: 'info',
    pending: 'warning',
    draft: 'neutral',
    trash: 'neutral',
    spam: 'danger',
    failed: 'danger',
};

// Picks a tone for a common status word. Unknown words are neutral.
export function statusTone(status: string): StatusTone {
    return Object.hasOwn(KNOWN_TONES, status) ? KNOWN_TONES[status] : 'neutral';
}

type Props = {
    tone: StatusTone;
    children: ReactNode;
    className?: string;
};

// Soft colored label with a small dot, for any status.
export function StatusBadge({ tone, children, className }: Props) {
    return (
        <span
            data-slot="status-badge"
            className={cn(
                'inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                TONES[tone],
                className,
            )}
        >
            <span
                aria-hidden="true"
                className={cn('size-1.5 rounded-full', DOTS[tone])}
            />
            {children}
        </span>
    );
}
