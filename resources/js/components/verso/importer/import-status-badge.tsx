import { StatusBadge } from '@/components/verso/ui/status-badge';
import type { StatusTone } from '@/components/verso/ui/status-badge';
import { cn } from '@/lib/utils';
import type { ImportStatus } from '@/types';

const TONES: Record<ImportStatus, StatusTone> = {
    pending: 'neutral',
    running: 'info',
    completed: 'success',
    failed: 'danger',
};

// Soft colored label for the status of an import run.
export function ImportStatusBadge({
    status,
    label,
}: {
    status: ImportStatus;
    label: string;
}) {
    return <StatusBadge tone={TONES[status]}>{label}</StatusBadge>;
}

// Thin bar that shows how much of an import is done.
export function ImportProgress({ percent }: { percent: number | null }) {
    const value = percent ?? 0;

    return (
        <div
            role="progressbar"
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={percent ?? undefined}
            className="h-2 w-full overflow-hidden rounded-full bg-muted"
        >
            <div
                className={cn(
                    'h-full rounded-full bg-brand transition-[width] duration-500 motion-reduce:transition-none',
                    percent === null && 'motion-safe:animate-pulse',
                )}
                style={{ width: `${percent === null ? 100 : value}%` }}
            />
        </div>
    );
}
