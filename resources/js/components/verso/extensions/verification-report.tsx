import type { ReactNode } from 'react';
import {
    DataTable,
    EmptyState,
    SectionCard,
    SiteDate,
    StatusBadge,
} from '@/components/verso/ui';
import type { DataTableColumn, StatusTone } from '@/components/verso/ui';
import { formatNumber, msg, t, Trans } from '@/lib/i18n';
import { formatSeconds, PreviewPages, ThemeChecksCard } from './theme-report';
import type {
    FindingSeverity,
    RiskLevel,
    SandboxRun,
    VerificationFinding,
    VerificationReport,
} from '@/types';

// Badge tone for each risk level.
export const RISK_TONES: Record<RiskLevel, StatusTone> = {
    low: 'success',
    medium: 'warning',
    high: 'danger',
};

// Badge tone for each finding severity.
export const SEVERITY_TONES: Record<FindingSeverity, StatusTone> = {
    high: 'danger',
    medium: 'warning',
    low: 'info',
    info: 'neutral',
};

const SEVERITY_ORDER: FindingSeverity[] = ['high', 'medium', 'low', 'info'];

const SEVERITY_LABELS: Record<FindingSeverity, string> = {
    high: msg('High'),
    medium: msg('Medium'),
    low: msg('Low'),
    info: msg('Info'),
};

// Plain words for each sandbox result.
const SANDBOX_LABELS: Record<SandboxRun['status'], string> = {
    passed: msg('No errors'),
    failed: msg('Failed'),
    timeout: msg('Stopped: took too long'),
    crashed: msg('Stopped without a result'),
    skipped: msg('Not run'),
};

const STAGE_LABELS: Record<string, string> = {
    setup: msg('Setup'),
    database: msg('Database'),
    manifest: msg('Manifest'),
    migrate: msg('Migrations'),
    rollback: msg('Rollback'),
    boot: msg('Boot'),
    seed: msg('Demo content'),
    theme: msg('Theme'),
    render: msg('Render'),
};

const SANDBOX_TONES: Record<SandboxRun['status'], StatusTone> = {
    passed: 'success',
    failed: 'danger',
    timeout: 'danger',
    crashed: 'danger',
    skipped: 'neutral',
};

// "Low risk", "Medium risk" or "High risk".
export function riskLabel(risk: RiskLevel): string {
    return t(
        '{risk, select, low {Low risk} medium {Medium risk} other {High risk}}',
        {
            risk,
        },
    );
}

// Where a finding is, like "src/Backup.php:12".
export function findingLocation(finding: VerificationFinding): string {
    if (finding.file === null) {
        return '';
    }

    return finding.line === null
        ? finding.file
        : `${finding.file}:${finding.line}`;
}

export function RiskBadge({ risk }: { risk: RiskLevel }) {
    return <StatusBadge tone={RISK_TONES[risk]}>{riskLabel(risk)}</StatusBadge>;
}

const findingColumns = (): DataTableColumn<VerificationFinding>[] => [
    {
        key: 'finding',
        header: t('Finding'),
        primary: true,
        wrap: true,
        cell: (finding) => (
            <span className="flex min-w-0 flex-col gap-0.5">
                <span className="text-body">{finding.message}</span>
                <span className="font-mono text-xs text-muted-foreground">
                    {finding.rule}
                </span>
            </span>
        ),
    },
    {
        key: 'severity',
        header: t('Severity'),
        className: 'w-28',
        cell: (finding) => (
            <StatusBadge tone={SEVERITY_TONES[finding.severity]}>
                {t(SEVERITY_LABELS[finding.severity])}
            </StatusBadge>
        ),
    },
    {
        key: 'location',
        header: t('File'),
        wrap: true,
        cell: (finding) => (
            <span className="font-mono text-xs break-all text-muted-foreground">
                {findingLocation(finding) || '-'}
            </span>
        ),
    },
];

type Props = {
    report: VerificationReport;
    capabilities?: Record<string, string>;
    actions?: ReactNode;
};

// The verification report of a plugin or theme: risk, findings, what blocks the install, permissions or theme checks, and the sandbox run.
export function VerificationReportCard({
    report,
    capabilities = {},
    actions,
}: Props) {
    const permissions = report.details.permissions;
    const sandbox = report.details.sandbox;
    const theme = report.details.theme;
    const noun = report.kind === 'theme' ? 'theme' : 'plugin';
    const blocking = report.blocking ?? [];
    const files = report.details.files;
    const checked =
        files === undefined
            ? t(
                  '{source, select, upload {Checked <date></date> from the uploaded zip.} registry {Checked <date></date> from the registry package.} other {Checked <date></date> from the installed files.}}',
                  { source: report.source },
              )
            : t(
                  '{source, select, upload {Checked <date></date> from the uploaded zip, {count, plural, one {# file} other {# files}} scanned.} registry {Checked <date></date> from the registry package, {count, plural, one {# file} other {# files}} scanned.} other {Checked <date></date> from the installed files, {count, plural, one {# file} other {# files}} scanned.}}',
                  { source: report.source, count: files },
              );

    return (
        <div className="flex min-w-0 flex-col gap-4">
            <SectionCard
                icon="shield"
                title={t('Verification')}
                description={
                    <Trans
                        text={checked}
                        components={{
                            date: () => <SiteDate iso={report.checkedAt} />,
                        }}
                    />
                }
                actions={
                    <div className="flex items-center gap-2">
                        <RiskBadge risk={report.risk} />
                        {actions}
                    </div>
                }
            >
                <dl className="grid grid-cols-2 gap-3 sm:grid-cols-5">
                    <div className="rounded-md border px-3 py-2">
                        <dt className="text-xs text-muted-foreground">
                            {t('Score')}
                        </dt>
                        <dd className="text-lg font-semibold text-foreground tabular-nums">
                            {formatNumber(report.score)}
                        </dd>
                    </div>
                    {SEVERITY_ORDER.map((severity) => (
                        <div
                            key={severity}
                            className="rounded-md border px-3 py-2"
                        >
                            <dt className="text-xs text-muted-foreground">
                                {t(SEVERITY_LABELS[severity])}
                            </dt>
                            <dd className="text-lg font-semibold text-foreground tabular-nums">
                                {formatNumber(report.counts[severity])}
                            </dd>
                        </div>
                    ))}
                </dl>

                {blocking.length > 0 && (
                    <div
                        role="alert"
                        className="mt-4 rounded-md bg-danger-soft px-3 py-2 text-sm text-danger-text"
                    >
                        <p className="font-medium">
                            {t(
                                '{kind, select, theme {This theme cannot be installed until these findings are fixed:} other {This plugin cannot be installed until these findings are fixed:}}',
                                { kind: noun },
                            )}
                        </p>
                        <ul className="mt-1 flex flex-wrap gap-1.5">
                            {blocking.map((rule) => (
                                <li key={rule} className="font-mono text-xs">
                                    {rule}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </SectionCard>

            <DataTable
                caption={t('Findings')}
                rows={report.findings}
                columns={findingColumns()}
                rowKey={(finding) =>
                    `${finding.rule}:${findingLocation(finding)}:${finding.message}`
                }
                empty={
                    <EmptyState
                        icon="shield"
                        title={t('No findings.')}
                        description={t(
                            '{kind, select, theme {The checks found nothing risky in this theme.} other {The checks found nothing risky in this plugin.}}',
                            { kind: noun },
                        )}
                    />
                }
            />

            <div className="masonry-grid">
                {permissions && (
                    <PermissionsCard
                        permissions={permissions}
                        capabilities={capabilities}
                    />
                )}
                {theme && <ThemeChecksCard theme={theme} />}
                {sandbox && <SandboxCard sandbox={sandbox} kind={noun} />}
            </div>

            {sandbox?.pages && <PreviewPages pages={sandbox.pages} />}
        </div>
    );
}

// Declared permissions and hosts next to what the code really uses.
function PermissionsCard({
    permissions,
    capabilities,
}: {
    permissions: NonNullable<VerificationReport['details']['permissions']>;
    capabilities: Record<string, string>;
}) {
    const undeclared = permissions.used.filter(
        (capability) => !permissions.declared.includes(capability),
    );

    return (
        <SectionCard
            title={t('Permissions and network')}
            description={t(
                'What verso.json asks for, compared with what the code uses.',
            )}
        >
            <dl className="grid gap-3 text-sm">
                <ListRow
                    label={t('Declared')}
                    values={permissions.declared.map(
                        (value) => capabilities[value] ?? value,
                    )}
                />
                <ListRow
                    label={t('Used')}
                    values={permissions.used.map(
                        (value) => capabilities[value] ?? value,
                    )}
                />
                {undeclared.length > 0 && (
                    <ListRow
                        label={t('Not declared')}
                        tone="danger"
                        values={undeclared.map(
                            (value) => capabilities[value] ?? value,
                        )}
                    />
                )}
                <ListRow
                    label={t('Allowed hosts')}
                    values={permissions.network}
                />
                <ListRow
                    label={t('Hosts in code')}
                    values={permissions.hosts}
                />
            </dl>
        </SectionCard>
    );
}

function ListRow({
    label,
    values,
    tone,
}: {
    label: string;
    values: string[];
    tone?: StatusTone;
}) {
    return (
        <div className="grid gap-1 sm:grid-cols-[8rem_minmax(0,1fr)] sm:gap-3">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="flex min-w-0 flex-wrap gap-1.5">
                {values.length === 0 ? (
                    <span className="text-muted-foreground">{t('None')}</span>
                ) : (
                    values.map((value) => (
                        <StatusBadge key={value} tone={tone ?? 'neutral'}>
                            <span className="break-all whitespace-normal">
                                {value}
                            </span>
                        </StatusBadge>
                    ))
                )}
            </dd>
        </div>
    );
}

// Result of booting the plugin, or rendering the theme, in a separate process with a temporary database.
function SandboxCard({
    sandbox,
    kind,
}: {
    sandbox: SandboxRun;
    kind: 'plugin' | 'theme';
}) {
    const isTheme = kind === 'theme';

    return (
        <SectionCard
            title={isTheme ? t('Sandbox render') : t('Sandbox boot')}
            description={
                isTheme
                    ? t(
                          'The theme rendered a home page, a post and the archive with demo posts and a temporary database.',
                      )
                    : t('The plugin booted alone with a temporary database.')
            }
            actions={
                <StatusBadge tone={SANDBOX_TONES[sandbox.status]}>
                    {t(SANDBOX_LABELS[sandbox.status])}
                </StatusBadge>
            }
        >
            {sandbox.status === 'skipped' ? (
                <p className="text-sm text-muted-foreground">
                    {isTheme
                        ? t(
                              'The theme was not rendered: the sandbox is off, or required files are missing.',
                          )
                        : t(
                              'The sandbox is turned off, so the plugin was not booted.',
                          )}
                </p>
            ) : isTheme ? (
                <dl className="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-2 text-sm">
                    <dt className="text-muted-foreground">{t('Pages')}</dt>
                    <dd className="tabular-nums">
                        {t('{rendered} of {total} rendered', {
                            rendered: (sandbox.pages ?? []).filter(
                                (page) => page.status === 200,
                            ).length,
                            total: (sandbox.pages ?? []).length,
                        })}
                    </dd>
                    <dt className="text-muted-foreground">{t('Time')}</dt>
                    <dd className="tabular-nums">
                        {formatSeconds(sandbox.seconds)}
                    </dd>
                </dl>
            ) : (
                <dl className="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-2 text-sm">
                    <dt className="text-muted-foreground">{t('Migrations')}</dt>
                    <dd className="tabular-nums">
                        {t('{up} up, {down} down', {
                            up: sandbox.migrated.length,
                            down: sandbox.rolledBack.length,
                        })}
                    </dd>
                    <dt className="text-muted-foreground">
                        {t('Routes added')}
                    </dt>
                    <dd className="tabular-nums">
                        {formatNumber(sandbox.routes.length)}
                    </dd>
                    <dt className="text-muted-foreground">{t('Time')}</dt>
                    <dd className="tabular-nums">
                        {formatSeconds(sandbox.seconds)}
                    </dd>
                </dl>
            )}
            {sandbox.errors.length > 0 && (
                <ul className="mt-3 flex flex-col gap-2">
                    {sandbox.errors.map((error) => (
                        <li
                            key={`${error.stage}:${error.message}`}
                            className="rounded-md bg-danger-soft px-3 py-2 text-sm break-words text-danger-text"
                        >
                            <span className="font-medium">
                                {t('{stage}:', {
                                    stage: STAGE_LABELS[error.stage]
                                        ? t(STAGE_LABELS[error.stage])
                                        : error.stage,
                                })}
                            </span>{' '}
                            {error.message}
                        </li>
                    ))}
                </ul>
            )}
        </SectionCard>
    );
}
