import { SectionCard, SiteDate } from '@/components/verso/ui';
import { formatList, t } from '@/lib/i18n';
import type { AccessAuditEntry, Capability } from '@/types';

type Props = {
    entries: AccessAuditEntry[];
    capabilities?: Capability[];
    roleNames?: Record<string, string>;
    title?: string;
    description?: string;
};

function text(value: unknown): string {
    return typeof value === 'string' || typeof value === 'number'
        ? String(value)
        : '';
}

function list(value: unknown): string[] {
    return Array.isArray(value) ? value.map(String) : [];
}

// A short sentence for one audit line, like "Given to Marta Ribeiro".
export function auditSummary(entry: AccessAuditEntry): string {
    const details = entry.details ?? {};
    const user = text(details.user);

    switch (entry.action) {
        case 'role.create':
            return details.based_on
                ? t('Created from {role}', { role: text(details.based_on) })
                : t('Created');
        case 'role.update':
            return details.renamed
                ? t('Renamed from {name}', { name: text(details.renamed) })
                : t('Changed capabilities');
        case 'role.delete': {
            const users = Number(details.users ?? 0);

            return users > 0
                ? t(
                      'Deleted and taken from {count, plural, one {# user} other {# users}}',
                      { count: users },
                  )
                : t('Deleted');
        }
        case 'role.reset':
            return t('Reset to defaults');
        case 'user.role':
            return entry.new === 'added'
                ? t('Given to {user}', { user })
                : t('Taken from {user}', { user });
        case 'user.access':
            return user
                ? t('Changed the access of {user}', { user })
                : t('Changed access');
        case 'role.view_as':
            return entry.new === 'stop'
                ? t('Stopped the preview')
                : t('Previewed as this role');
        default:
            return entry.action;
    }
}

// Recent role and access changes: who, when and what.
export function AccessAuditCard({
    entries,
    capabilities = [],
    roleNames,
    title = t('Recent changes'),
    description = t('Who changed what, newest first.'),
}: Props) {
    const labels = new Map(
        capabilities.map((capability) => [capability.key, capability.label]),
    );
    const names = (keys: string[]) =>
        formatList(keys.map((key) => labels.get(key) ?? key));

    return (
        <SectionCard icon="clock" title={title} description={description}>
            {entries.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('No change yet.')}
                </p>
            ) : (
                <ol className="grid gap-3 text-sm">
                    {entries.map((entry) => {
                        const added = list(entry.details?.added);
                        const removed = list(entry.details?.removed);
                        const roleName =
                            roleNames &&
                            entry.key &&
                            !entry.key.startsWith('user:')
                                ? (roleNames[entry.key] ??
                                  (text(entry.details?.name) || entry.key))
                                : null;

                        return (
                            <li
                                key={entry.id}
                                className="grid min-w-0 gap-0.5 border-b pb-3 last:border-b-0 last:pb-0"
                            >
                                <p className="min-w-0 break-words">
                                    {roleName && (
                                        <span className="font-medium">
                                            {roleName}:{' '}
                                        </span>
                                    )}
                                    {auditSummary(entry)}
                                </p>
                                {added.length > 0 && (
                                    <p className="break-words text-success-text">
                                        {t('Added: {capabilities}', {
                                            capabilities: names(added),
                                        })}
                                    </p>
                                )}
                                {removed.length > 0 && (
                                    <p className="break-words text-danger-text">
                                        {t('Removed: {capabilities}', {
                                            capabilities: names(removed),
                                        })}
                                    </p>
                                )}
                                <p className="text-xs text-muted-foreground">
                                    {entry.user ?? t('Someone')}
                                    {entry.at && ', '}
                                    <SiteDate iso={entry.at} />
                                </p>
                            </li>
                        );
                    })}
                </ol>
            )}
        </SectionCard>
    );
}
