import { StatusBadge } from '@/components/verso/ui/status-badge';
import type { StatusTone } from '@/components/verso/ui/status-badge';
import { msg, t } from '@/lib/i18n';
import type { EntryStatus } from '@/types';

const LABELS: Record<EntryStatus, string> = {
    published: msg('Published'),
    scheduled: msg('Scheduled'),
    draft: msg('Draft'),
    trash: msg('Trash'),
};

const TONES: Record<EntryStatus, StatusTone> = {
    published: 'success',
    scheduled: 'info',
    draft: 'neutral',
    trash: 'danger',
};

// Soft colored label for an entry status.
export function EntryStatusBadge({ status }: { status: EntryStatus }) {
    return (
        <StatusBadge tone={TONES[status]}>
            {LABELS[status] ? t(LABELS[status]) : status}
        </StatusBadge>
    );
}
