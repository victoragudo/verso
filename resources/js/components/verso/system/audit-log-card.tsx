import { SectionCard, SiteDate } from '@/components/verso/ui';
import { formatList, t, Trans } from '@/lib/i18n';
import type { AuditEntry } from './types';

function show(value: unknown): string {
    if (value === null || value === undefined) {
        return t('empty');
    }

    if (typeof value === 'object') {
        return Array.isArray(value)
            ? formatList(value.map(String))
            : formatList(
                  Object.entries(value as Record<string, unknown>).map(
                      ([key, item]) => `${key} ${JSON.stringify(item)}`,
                  ),
              );
    }

    return typeof value === 'string' ? value : JSON.stringify(value);
}

function summary(entry: AuditEntry): string {
    const user = entry.user ?? t('Someone');
    const setting = entry.label ?? entry.key ?? '';

    switch (entry.action) {
        case 'update':
            return t(
                '<user>{user}</user> changed <setting>{setting}</setting>',
                {
                    user,
                    setting,
                },
            );
        case 'reset':
            return t('<user>{user}</user> reset <setting>{setting}</setting>', {
                user,
                setting,
            });
        case 'ini_write':
            return t(
                '<user>{user}</user> wrote PHP limits to <file>{file}</file>',
                {
                    user,
                    file: show(entry.details?.file),
                },
            );
        default:
            return t('<user>{user}</user> fixed permissions', { user });
    }
}

// The last changes made in System settings and on the Server health page.
export function AuditLogCard({ entries }: { entries: AuditEntry[] }) {
    return (
        <SectionCard
            icon="clock"
            title={t('Recent changes')}
            description={t('Who changed what, newest first.')}
        >
            {entries.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('No change yet.')}
                </p>
            ) : (
                <ol className="grid gap-3 text-sm">
                    {entries.map((entry) => (
                        <li
                            key={entry.id}
                            className="grid min-w-0 gap-0.5 border-b pb-3 last:border-b-0 last:pb-0"
                        >
                            <p className="min-w-0 break-words">
                                <Trans
                                    text={summary(entry)}
                                    components={{
                                        user: (text) => (
                                            <span className="font-medium">
                                                {text}
                                            </span>
                                        ),
                                        setting: (text) => (
                                            <span className="font-medium">
                                                {text}
                                            </span>
                                        ),
                                        file: (text) => (
                                            <span className="font-mono text-xs">
                                                {text}
                                            </span>
                                        ),
                                    }}
                                />
                            </p>
                            {(entry.action === 'update' ||
                                entry.action === 'reset') && (
                                <p className="break-words text-muted-foreground tabular-nums">
                                    {show(entry.old)} → {show(entry.new)}
                                </p>
                            )}
                            {entry.action === 'ini_write' && (
                                <p className="break-words text-muted-foreground">
                                    {show(entry.new)}
                                </p>
                            )}
                            <SiteDate
                                iso={entry.at}
                                className="text-xs text-muted-foreground"
                            />
                        </li>
                    ))}
                </ol>
            )}
        </SectionCard>
    );
}
