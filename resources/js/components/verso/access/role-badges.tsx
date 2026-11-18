import { StatusBadge } from '@/components/verso/ui';
import { t } from '@/lib/i18n';
import type { AccessRole } from '@/types';

// Built-in or Custom, plus Modified when a built-in role was changed.
export function RoleBadges({ role }: { role: AccessRole }) {
    return (
        <span className="inline-flex flex-wrap gap-1">
            {role.builtIn ? (
                <StatusBadge tone="neutral">{t('Built-in')}</StatusBadge>
            ) : (
                <StatusBadge tone="info">{t('Custom')}</StatusBadge>
            )}
            {role.modified && (
                <StatusBadge tone="warning">{t('Modified')}</StatusBadge>
            )}
        </span>
    );
}
