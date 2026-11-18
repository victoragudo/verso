import type { StatusTone } from '@/components/verso/ui';
import { formatBytes as formatByteSize, msg, t } from '@/lib/i18n';
import type { CoreUpdateStatus, CoreUpdateStepStatus } from '@/types';

const RUN_TONES: Record<CoreUpdateStatus, StatusTone> = {
    pending: 'neutral',
    running: 'info',
    completed: 'success',
    failed: 'danger',
    rolled_back: 'warning',
};

const STEP_TONES: Record<CoreUpdateStepStatus, StatusTone> = {
    pending: 'neutral',
    running: 'info',
    done: 'success',
    failed: 'danger',
    skipped: 'neutral',
    warning: 'warning',
};

const STEP_LABELS: Record<CoreUpdateStepStatus, string> = {
    pending: msg('Waiting'),
    running: msg('Running'),
    done: msg('Done'),
    failed: msg('Failed'),
    skipped: msg('Skipped'),
    warning: msg('Warning'),
};

// Badge tone for the state of a whole update run.
export function updateStatusTone(status: CoreUpdateStatus): StatusTone {
    return Object.hasOwn(RUN_TONES, status) ? RUN_TONES[status] : 'neutral';
}

// Badge tone for one step of an update run.
export function stepTone(status: CoreUpdateStepStatus): StatusTone {
    return Object.hasOwn(STEP_TONES, status) ? STEP_TONES[status] : 'neutral';
}

export function stepLabel(status: CoreUpdateStepStatus): string {
    return Object.hasOwn(STEP_LABELS, status) ? t(STEP_LABELS[status]) : status;
}

// How many steps are finished, from 0 to 100, for the progress bar.
export function progressPercent(
    steps: { status: CoreUpdateStepStatus; key: string }[],
): number {
    const planned = steps.filter((step) => step.key !== 'rollback');

    if (planned.length === 0) {
        return 0;
    }

    const finished = planned.filter((step) =>
        ['done', 'skipped', 'warning'].includes(step.status),
    ).length;

    return Math.round((finished * 100) / planned.length);
}

// Readable size of a package, like "17.5 MB".
export function formatBytes(bytes: number | null): string | null {
    if (bytes === null || !Number.isFinite(bytes) || bytes < 0) {
        return null;
    }

    return formatByteSize(bytes);
}
