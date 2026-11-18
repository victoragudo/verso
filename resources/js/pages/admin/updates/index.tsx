import { Head, router, useForm, usePoll } from '@inertiajs/react';
import { ExternalLink, RefreshCw } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    DataTable,
    EmptyState,
    NoticeBanner,
    PageHeader,
    SafeHtml,
    SectionCard,
    StatusBadge,
} from '@/components/verso/ui';
import type { DataTableColumn } from '@/components/verso/ui';
import { UpdateProgress } from '@/components/verso/updates/update-progress';
import {
    formatBytes,
    updateStatusTone,
} from '@/components/verso/updates/update-status';
import { useSiteDates } from '@/hooks/use-site-dates';
import { msg, t } from '@/lib/i18n';
import { check, index, settings, store } from '@/routes/admin/updates';
import type { CoreRelease, CoreUpdateDetail, CoreUpdateSummary } from '@/types';

type Option = { value: string; label: string; description: string };

type Props = {
    check: {
        configured: boolean;
        currentVersion: string;
        latest: CoreRelease | null;
        updateAvailable: boolean;
        checkedAt: string | null;
        error: string | null;
    };
    settings: { channel: string; automatic: string };
    channels: Option[];
    automaticOptions: Option[];
    databaseBackupProblem: string | null;
    signatureRequired: boolean;
    activeUpdate: CoreUpdateDetail | null;
    updates: CoreUpdateSummary[];
    pollInterval: number;
};

// Core updates: running and newest version, changelog, one click update with live progress, settings and the log.
export default function UpdatesIndex(props: Props) {
    const { formatDate } = useSiteDates();
    const running = props.activeUpdate !== null && !props.activeUpdate.finished;
    const [checking, setChecking] = useState(false);

    const runCheck = () =>
        router.post(
            check.url(),
            {},
            {
                preserveScroll: true,
                onStart: () => setChecking(true),
                onFinish: () => setChecking(false),
            },
        );

    return (
        <>
            <Head title={t('Updates')} />
            <PageHeader
                icon="updates"
                title={t('Updates')}
                description={t(
                    'Keep Verso up to date. Your content, settings, uploads, plugins and themes stay as they are.',
                )}
                actions={
                    props.check.configured && (
                        <Button
                            variant="outline"
                            className="min-h-10 md:min-h-9"
                            onClick={runCheck}
                            disabled={checking || running}
                        >
                            <RefreshCw
                                className={checking ? 'animate-spin' : ''}
                            />
                            {t('Check now')}
                        </Button>
                    )
                }
            />

            {running && <LivePoll interval={props.pollInterval} />}

            <div className="grid items-start gap-4 @3xl/page:grid-cols-[minmax(0,1fr)_clamp(20rem,28cqi,32rem)]">
                <div className="flex min-w-0 flex-col gap-4">
                    <VersionCard {...props} formatDate={formatDate} />

                    {props.activeUpdate && (
                        <SectionCard
                            title={
                                running
                                    ? t('Update in progress')
                                    : t('Last update')
                            }
                            icon="updates"
                            description={
                                running
                                    ? t(
                                          'This page refreshes on its own. Visitors see a maintenance page for a short time.',
                                      )
                                    : undefined
                            }
                        >
                            <UpdateProgress update={props.activeUpdate} />
                        </SectionCard>
                    )}

                    {props.check.latest && (
                        <ReleaseCard
                            release={props.check.latest}
                            available={props.check.updateAvailable}
                            running={running}
                            databaseBackupProblem={props.databaseBackupProblem}
                            signatureRequired={props.signatureRequired}
                            formatDate={formatDate}
                        />
                    )}

                    <UpdateLog
                        updates={props.updates}
                        formatDate={formatDate}
                    />
                </div>

                <div className="flex min-w-0 flex-col gap-4">
                    <SettingsCard
                        settings={props.settings}
                        channels={props.channels}
                        automaticOptions={props.automaticOptions}
                    />
                </div>
            </div>
        </>
    );
}

// Refreshes the progress and the log while an update runs.
function LivePoll({ interval }: { interval: number }) {
    const { stop } = usePoll(interval, {
        only: ['activeUpdate', 'updates', 'check'],
    });

    useEffect(() => stop, [stop]);

    return null;
}

type DateFormatter = (iso: string | null) => string;

function VersionCard({
    check: status,
    formatDate,
}: Props & { formatDate: DateFormatter }) {
    const latest = status.latest;

    return (
        <SectionCard title={t('Your version')} icon="site">
            <div className="flex flex-col gap-4">
                {!status.configured && (
                    <NoticeBanner
                        tone="neutral"
                        title={t('Update checks are off')}
                        description={t(
                            'Set VERSO_UPDATES_FEED_URL to a release feed to turn them on.',
                        )}
                    />
                )}
                {status.error && (
                    <NoticeBanner
                        tone="danger"
                        title={t('The last check failed')}
                        description={status.error}
                    />
                )}
                <dl className="grid gap-4 sm:grid-cols-3">
                    <Detail label={t('Running')}>
                        <span className="text-lg font-semibold text-foreground tabular-nums">
                            {status.currentVersion}
                        </span>
                    </Detail>
                    <Detail label={t('Newest release')}>
                        <span className="flex flex-wrap items-center gap-2">
                            <span className="text-lg font-semibold text-foreground tabular-nums">
                                {latest?.version ?? t('Unknown')}
                            </span>
                            {status.updateAvailable ? (
                                <StatusBadge
                                    tone={latest?.security ? 'warning' : 'info'}
                                >
                                    {latest?.security
                                        ? t('Security update')
                                        : t('Update available')}
                                </StatusBadge>
                            ) : (
                                latest && (
                                    <StatusBadge tone="success">
                                        {t('Up to date')}
                                    </StatusBadge>
                                )
                            )}
                        </span>
                    </Detail>
                    <Detail label={t('Last checked')}>
                        <span className="text-sm text-foreground tabular-nums">
                            {status.checkedAt
                                ? formatDate(status.checkedAt)
                                : t('Not checked yet')}
                        </span>
                    </Detail>
                </dl>
            </div>
        </SectionCard>
    );
}

function Detail({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="min-w-0">
            <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </dt>
            <dd className="mt-1">{children}</dd>
        </div>
    );
}

type ReleaseCardProps = {
    release: CoreRelease;
    available: boolean;
    running: boolean;
    databaseBackupProblem: string | null;
    signatureRequired: boolean;
    formatDate: DateFormatter;
};

// The changelog of the newest release and the "Update now" button.
function ReleaseCard({
    release,
    available,
    running,
    databaseBackupProblem,
    signatureRequired,
    formatDate,
}: ReleaseCardProps) {
    const form = useForm({
        version: release.version,
        allow_without_database_backup: false,
    });
    const needsConsent = databaseBackupProblem !== null;
    const blocked =
        running ||
        form.processing ||
        (needsConsent && !form.data.allow_without_database_backup);
    const size = formatBytes(release.package_size);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(store.url(), { preserveScroll: true });
    };

    return (
        <SectionCard
            title={t('What is new in {release}', { release: release.name })}
            icon="entries"
            description={[
                release.published_at
                    ? t('Released {date}', {
                          date: formatDate(release.published_at),
                      })
                    : null,
                size,
                release.pre_release ? t('Test version') : null,
            ]
                .filter(Boolean)
                .join(' · ')}
            actions={
                release.page_url && (
                    <a
                        href={release.page_url}
                        target="_blank"
                        rel="noreferrer noopener"
                        className="inline-flex min-h-10 items-center gap-1 text-sm font-medium text-brand hover:underline md:min-h-0"
                    >
                        {t('Release page')}
                        <ExternalLink className="size-3.5" aria-hidden="true" />
                    </a>
                )
            }
            footer={
                available && (
                    <form
                        onSubmit={submit}
                        className="flex w-full flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <p className="text-xs text-muted-foreground">
                            {signatureRequired
                                ? t(
                                      'The package is checked with its sha256 sum and signature before anything changes.',
                                  )
                                : t(
                                      'The package is checked with its sha256 sum before anything changes.',
                                  )}
                        </p>
                        <Button
                            type="submit"
                            disabled={blocked}
                            className="min-h-10 shrink-0 md:min-h-9"
                        >
                            {t('Update to {version}', {
                                version: release.version,
                            })}
                        </Button>
                    </form>
                )
            }
        >
            <div className="flex flex-col gap-4">
                {available && needsConsent && (
                    <div className="rounded-md bg-warning-soft px-3 py-3 text-sm text-warning-text">
                        <p className="font-medium">
                            {t('The database cannot be backed up')}
                        </p>
                        <p className="mt-0.5">{databaseBackupProblem}</p>
                        <div className="mt-3 flex items-start gap-2">
                            <Checkbox
                                id="allow_without_database_backup"
                                checked={
                                    form.data.allow_without_database_backup
                                }
                                onCheckedChange={(checked) =>
                                    form.setData(
                                        'allow_without_database_backup',
                                        checked === true,
                                    )
                                }
                            />
                            <Label
                                htmlFor="allow_without_database_backup"
                                className="leading-snug font-normal"
                            >
                                {t(
                                    'I have my own database backup. Update without one.',
                                )}
                            </Label>
                        </div>
                    </div>
                )}
                {form.errors.version && (
                    <p className="text-sm text-danger-text">
                        {form.errors.version}
                    </p>
                )}
                {release.notes_html ? (
                    <SafeHtml
                        className="release-notes max-w-none text-sm leading-relaxed text-foreground [&_a]:text-brand [&_a]:underline [&_code]:rounded [&_code]:bg-muted [&_code]:px-1 [&_h1]:mt-4 [&_h1]:text-base [&_h1]:font-semibold [&_h2]:mt-4 [&_h2]:text-sm [&_h2]:font-semibold [&_h3]:mt-3 [&_h3]:font-semibold [&_li]:mt-1 [&_ol]:list-decimal [&_ol]:ps-5 [&_p]:mt-2 [&_ul]:list-disc [&_ul]:ps-5 [&>*:first-child]:mt-0"
                        html={release.notes_html}
                    />
                ) : (
                    <p className="text-sm text-muted-foreground">
                        {t('This release has no notes.')}
                    </p>
                )}
            </div>
        </SectionCard>
    );
}

function SettingsCard({
    settings: current,
    channels,
    automaticOptions,
}: {
    settings: Props['settings'];
    channels: Option[];
    automaticOptions: Option[];
}) {
    const form = useForm(current);
    const channel = channels.find(
        (option) => option.value === form.data.channel,
    );
    const automatic = automaticOptions.find(
        (option) => option.value === form.data.automatic,
    );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(settings.url(), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit}>
            <SectionCard
                title={t('Settings')}
                icon="settings"
                contentClassName="flex flex-col gap-5"
                footer={
                    <Button
                        type="submit"
                        disabled={form.processing || !form.isDirty}
                        className="min-h-10 md:min-h-9"
                    >
                        {t('Save settings')}
                    </Button>
                }
            >
                <div className="flex flex-col gap-2">
                    <Label htmlFor="channel">{t('Update channel')}</Label>
                    <Select
                        value={form.data.channel}
                        onValueChange={(value) =>
                            form.setData('channel', value)
                        }
                    >
                        <SelectTrigger id="channel" className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {channels.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <p className="text-xs text-muted-foreground">
                        {channel?.description}
                    </p>
                    {form.errors.channel && (
                        <p className="text-sm text-danger-text">
                            {form.errors.channel}
                        </p>
                    )}
                </div>
                <div className="flex flex-col gap-2">
                    <Label htmlFor="automatic">{t('Automatic updates')}</Label>
                    <Select
                        value={form.data.automatic}
                        onValueChange={(value) =>
                            form.setData('automatic', value)
                        }
                    >
                        <SelectTrigger id="automatic" className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {automaticOptions.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <p className="text-xs text-muted-foreground">
                        {automatic?.description}{' '}
                        {t('New major versions always wait for you.')}
                    </p>
                    {form.errors.automatic && (
                        <p className="text-sm text-danger-text">
                            {form.errors.automatic}
                        </p>
                    )}
                </div>
            </SectionCard>
        </form>
    );
}

function UpdateLog({
    updates,
    formatDate,
}: {
    updates: CoreUpdateSummary[];
    formatDate: DateFormatter;
}) {
    const columns: DataTableColumn<CoreUpdateSummary>[] = [
        {
            key: 'version',
            header: t('Version'),
            primary: true,
            cell: (row) => (
                <span className="tabular-nums">
                    {t('{from} to {to}', {
                        from: row.from_version,
                        to: row.to_version,
                    })}
                </span>
            ),
        },
        {
            key: 'status',
            header: t('Status'),
            cell: (row) => (
                <StatusBadge tone={updateStatusTone(row.status)}>
                    {row.status_label}
                </StatusBadge>
            ),
        },
        {
            key: 'by',
            header: t('Started by'),
            cell: (row) => row.user ?? row.trigger_label,
        },
        {
            key: 'date',
            header: t('Date'),
            className: 'whitespace-nowrap tabular-nums',
            cell: (row) => formatDate(row.started_at ?? row.created_at),
        },
        {
            key: 'error',
            header: t('Notes'),
            wrap: true,
            hideOnMobile: true,
            className: 'text-muted-foreground',
            cell: (row) => row.error ?? '',
        },
    ];

    return (
        <SectionCard
            title={t('Update log')}
            icon="clock"
            contentClassName="p-0"
        >
            <DataTable
                rows={updates}
                columns={columns}
                rowKey={(row) => row.id}
                caption={t('Core updates, newest first')}
                empty={
                    <EmptyState
                        icon="updates"
                        title={t('No updates yet')}
                        description={t(
                            'Every update and its steps will be listed here.',
                        )}
                        className="py-10"
                    />
                }
            />
        </SectionCard>
    );
}

UpdatesIndex.layout = {
    breadcrumbs: [
        {
            title: msg('Updates'),
            href: index(),
        },
    ],
};
