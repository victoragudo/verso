import type { StatusTone } from '@/components/verso/ui';
import { msg, t } from '@/lib/i18n';
import type { SubmissionStatus } from './types';

// Badge colors of the submission statuses.
export const STATUS_TONES: Record<SubmissionStatus, StatusTone> = {
    new: 'info',
    read: 'neutral',
    archived: 'neutral',
    spam: 'danger',
};

const STATUS_LABELS: Record<SubmissionStatus, string> = {
    new: msg('forms::New'),
    read: msg('forms::Read'),
    archived: msg('forms::Archived'),
    spam: msg('forms::Spam'),
};

export function statusLabel(status: SubmissionStatus): string {
    return t(STATUS_LABELS[status]);
}
