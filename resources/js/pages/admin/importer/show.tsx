import { Head, Link, usePoll } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useEffect } from 'react';
import { Button } from '@/components/ui/button';
import {
    ImportProgress,
    ImportStatusBadge,
} from '@/components/verso/importer/import-status-badge';
import {
    DataTable,
    PageHeader,
    SectionCard,
    StatCard,
} from '@/components/verso/ui';
import type { DataTableColumn } from '@/components/verso/ui';
import { useSiteDates } from '@/hooks/use-site-dates';
import { formatNumber, msg, t } from '@/lib/i18n';
import { index, show } from '@/routes/admin/import';
import type { ImportDetail, ImportReportRow } from '@/types';

type Props = {
    import: ImportDetail;
    pollInterval: number;
};

type ReportCount = 'created' | 'updated' | 'skipped' | 'failed';

const KIND_LABELS: Record<string, string> = {
    authors: msg('Authors'),
    terms: msg('Categories and tags'),
    media: msg('Images and files'),
    entries: msg('Posts and pages'),
    comments: msg('Comments'),
    redirects: msg('Redirects'),
};

const COUNT_LABELS: Record<ReportCount, string> = {
    created: msg('Created'),
    updated: msg('Updated'),
    skipped: msg('Skipped'),
    failed: msg('Failed'),
};

const COUNT_KEYS = Object.keys(COUNT_LABELS) as ReportCount[];

const reportColumns = (): DataTableColumn<ImportReportRow>[] => [
    {
        key: 'kind',
        header: t('What'),
        primary: true,
        className: 'w-full',
        cell: (row) =>
            KIND_LABELS[row.kind] ? t(KIND_LABELS[row.kind]) : row.kind,
    },
    ...COUNT_KEYS.map((count) => ({
        key: count,
        header: t(COUNT_LABELS[count]),
        className: 'text-end',
        cell: (row: ImportReportRow) =>
            count === 'failed' && row.failed > 0 ? (
                <span className="font-medium text-danger-text">
                    {formatNumber(row.failed)}
                </span>
            ) : (
                formatNumber(row[count])
            ),
    })),
];

// Follows one import run: progress while it runs, then the report.
export default function ImportShow({ import: run, pollInterval }: Props) {
    const { formatDate } = useSiteDates();
    const finished = run.status === 'completed' || run.status === 'failed';
    const { stop } = usePoll(pollInterval, { only: ['import'] });

    useEffect(() => {
        if (finished) {
            stop();
        }
    }, [finished, stop]);

    return (
        <>
            <Head title={t('Import {file}', { file: run.original_name })} />
            <PageHeader
                icon="importer"
                title={<span className="break-all">{run.original_name}</span>}
                description={
                    run.user
                        ? t('Uploaded {date} by {user}.', {
                              date: formatDate(run.created_at),
                              user: run.user,
                          })
                        : t('Uploaded {date}.', {
                              date: formatDate(run.created_at),
                          })
                }
                actions={
                    <Button
                        variant="outline"
                        asChild
                        className="min-h-10 md:min-h-9"
                    >
                        <Link href={index.url()}>
                            <ArrowLeft />
                            {t('All imports')}
                        </Link>
                    </Button>
                }
            />

            <Summary run={run} />

            <div className="mt-6 grid gap-4">
                <SectionCard
                    title={t('Progress')}
                    actions={
                        <ImportStatusBadge
                            status={run.status}
                            label={run.status_label}
                        />
                    }
                >
                    <div className="flex flex-col gap-3">
                        <ImportProgress
                            percent={run.status === 'pending' ? 0 : run.percent}
                        />
                        <p className="text-sm text-muted-foreground tabular-nums">
                            {statusLine(run, formatDate)}
                        </p>
                        {run.error && (
                            <p className="rounded-md bg-danger-soft px-3 py-2 text-sm break-words text-danger-text">
                                {run.error}
                            </p>
                        )}
                    </div>
                </SectionCard>

                {run.report && (
                    <ReportSection
                        rows={run.report.rows ?? []}
                        messages={run.report.messages ?? []}
                        dropped={run.report.dropped_messages}
                    />
                )}
            </div>
        </>
    );
}

// Count cards: items done, then the totals of the report when it exists.
function Summary({ run }: { run: ImportDetail }) {
    const rows = run.report?.rows ?? [];
    const totals = COUNT_KEYS.map((count) => ({
        key: count,
        value: rows.reduce((sum, row) => sum + row[count], 0),
    }));

    return (
        <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-5">
            <StatCard
                label={t('Items done')}
                value={run.processed}
                hint={
                    run.total === null
                        ? undefined
                        : t('of {total}', { total: run.total })
                }
            />
            {run.report &&
                totals.map((total) => (
                    <StatCard
                        key={total.key}
                        label={t(COUNT_LABELS[total.key])}
                        value={total.value}
                    />
                ))}
        </div>
    );
}

// Counts per kind of content, then the warnings log.
function ReportSection({
    rows,
    messages,
    dropped,
}: {
    rows: ImportReportRow[];
    messages: string[];
    dropped: number;
}) {
    return (
        <>
            <section className="min-w-0">
                <h2 className="mb-3 text-sm font-semibold text-foreground">
                    {t('Report')}
                </h2>
                <DataTable
                    rows={rows}
                    columns={reportColumns()}
                    rowKey={(row) => row.kind}
                    caption={t('Import report')}
                />
            </section>

            {messages.length > 0 && (
                <SectionCard
                    title={t('Warnings')}
                    description={
                        dropped > 0
                            ? t(
                                  '{count, plural, one {# more warning was not shown.} other {# more warnings were not shown.}}',
                                  { count: dropped },
                              )
                            : undefined
                    }
                    contentClassName="p-0"
                >
                    <ul className="divide-y text-sm">
                        {messages.map((message, position) => (
                            <li
                                key={position}
                                className="flex items-start gap-3 px-4 py-2.5 break-words text-body sm:px-5"
                            >
                                <span
                                    aria-hidden="true"
                                    className="mt-1.5 size-1.5 shrink-0 rounded-full bg-warning-text"
                                />
                                <span className="min-w-0">{message}</span>
                            </li>
                        ))}
                    </ul>
                </SectionCard>
            )}
        </>
    );
}

function statusLine(
    run: ImportDetail,
    formatDate: (iso: string | null) => string,
): string {
    switch (run.status) {
        case 'pending':
            return t('Waiting for the queue to start the import.');
        case 'running':
            return run.total
                ? t('{processed} of {total} items done.', {
                      processed: run.processed,
                      total: run.total,
                  })
                : t('Reading the file.');
        case 'completed':
            return run.duration !== null
                ? t('Finished {date} in {duration}.', {
                      date: formatDate(run.finished_at),
                      duration: formatNumber(run.duration, {
                          style: 'unit',
                          unit: 'second',
                          unitDisplay: 'short',
                      }),
                  })
                : t('Finished {date}.', { date: formatDate(run.finished_at) });
        case 'failed':
            return t('Stopped {date}.', { date: formatDate(run.finished_at) });
    }
}

ImportShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: t('Import'), href: index() },
        { title: props.import.original_name, href: show(props.import.id) },
    ],
});
