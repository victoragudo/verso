import { StatusBadge } from '@/components/verso/ui/status-badge';
import type { StatusTone } from '@/components/verso/ui/status-badge';
import { msg, t } from '@/lib/i18n';
import type { CommentStatus } from '@/types';

const TONES: Record<CommentStatus, StatusTone> = {
    approved: 'success',
    pending: 'warning',
    spam: 'danger',
    trash: 'neutral',
};

const LABELS: Record<CommentStatus, string> = {
    approved: msg('Approved'),
    pending: msg('Pending'),
    spam: msg('Spam'),
    trash: msg('Trash'),
};

// Soft colored label for a comment status.
export function CommentStatusBadge({ status }: { status: CommentStatus }) {
    return <StatusBadge tone={TONES[status]}>{t(LABELS[status])}</StatusBadge>;
}
