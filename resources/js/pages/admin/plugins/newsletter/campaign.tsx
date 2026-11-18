import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    DataTable,
    EmptyState,
    PageHeader,
    SectionCard,
    SiteDate,
    StatCard,
} from '@/components/verso/ui';
import type { DataTableColumn } from '@/components/verso/ui';
import { QueueNotice } from '@/components/verso/newsletter/queue-notice';
import type { QueueNoticeData } from '@/components/verso/newsletter/queue-notice';
import { formatNumber, formatPercent, msg, t, Trans } from '@/lib/i18n';

type Stats = {
    recipients: number;
    sent: number;
    failed: number;
    queued: number;
    opens: number;
    clicks: number;
};

type LinkRow = { id: number; url: string; clicks: number };

type Props = {
    queueNotice?: QueueNoticeData | null;
    campaign: {
        id: number;
        subject: string;
        status: 'draft' | 'sending' | 'sent';
        list: string | null;
        track_opens: boolean;
        track_clicks: boolean;
        started_at: string | null;
        sent_at: string | null;
    };
    stats: Stats;
    links: LinkRow[];
    urls: { index: string; edit: string };
};

// A share of the sent emails, like "42%".
function rate(part: number, total: number): string {
    return formatPercent(total === 0 ? 0 : part / total, 0);
}

// Delivery, open and click numbers of one campaign.
export default function CampaignStats({
    campaign,
    stats,
    links,
    urls,
    queueNotice = null,
}: Props) {
    const columns: DataTableColumn<LinkRow>[] = [
        {
            key: 'url',
            header: t('newsletter::Link'),
            primary: true,
            className: 'max-w-0 w-full',
            cell: (link) => (
                <span className="block truncate font-mono text-xs">
                    {link.url}
                </span>
            ),
        },
        {
            key: 'clicks',
            header: t('newsletter::Clicks'),
            className: 'tabular-nums md:text-end',
            cell: (link) => formatNumber(link.clicks),
        },
    ];

    return (
        <>
            <Head title={campaign.subject} />
            <PageHeader
                icon="newsletter"
                title={campaign.subject}
                description={
                    <Trans
                        text={
                            campaign.sent_at
                                ? campaign.list === null
                                    ? t(
                                          'newsletter::Sent to all subscribers on <date></date>.',
                                      )
                                    : t(
                                          'newsletter::Sent to {list} on <date></date>.',
                                          { list: campaign.list },
                                      )
                                : campaign.list === null
                                  ? t(
                                        'newsletter::Sent to all subscribers, still sending in batches.',
                                    )
                                  : t(
                                        'newsletter::Sent to {list}, still sending in batches.',
                                        { list: campaign.list },
                                    )
                        }
                        components={{
                            date: () => <SiteDate iso={campaign.sent_at} />,
                        }}
                    />
                }
                actions={
                    <Button asChild variant="outline">
                        <Link href={urls.index}>
                            {t('newsletter::All campaigns')}
                        </Link>
                    </Button>
                }
            />
            <QueueNotice notice={queueNotice} />

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    label={t('newsletter::Recipients')}
                    value={stats.recipients}
                    icon="users"
                />
                <StatCard
                    label={t('newsletter::Delivered')}
                    value={stats.sent}
                    hint={t('newsletter::{queued} waiting, {failed} failed', {
                        queued: formatNumber(stats.queued),
                        failed: formatNumber(stats.failed),
                    })}
                    icon="newsletter"
                />
                <StatCard
                    label={t('newsletter::Opens')}
                    value={
                        campaign.track_opens
                            ? rate(stats.opens, stats.sent)
                            : t('newsletter::Off')
                    }
                    hint={
                        campaign.track_opens
                            ? t(
                                  'newsletter::{count, plural, one {# person} other {# people}}',
                                  { count: stats.opens },
                              )
                            : t('newsletter::Open tracking was off')
                    }
                    icon="dashboard"
                />
                <StatCard
                    label={t('newsletter::Clicks')}
                    value={
                        campaign.track_clicks
                            ? rate(stats.clicks, stats.sent)
                            : t('newsletter::Off')
                    }
                    hint={
                        campaign.track_clicks
                            ? t(
                                  'newsletter::{count, plural, one {# person} other {# people}}',
                                  { count: stats.clicks },
                              )
                            : t('newsletter::Click tracking was off')
                    }
                    icon="redirects"
                />
            </div>

            {campaign.track_clicks && (
                <SectionCard
                    title={t('newsletter::Links')}
                    className="mt-4"
                    contentClassName="p-0 sm:p-0"
                >
                    <DataTable
                        rows={links}
                        columns={columns}
                        rowKey={(link) => link.id}
                        caption={t('newsletter::Clicks per link')}
                        empty={
                            <EmptyState
                                icon="redirects"
                                title={t(
                                    'newsletter::This campaign has no links.',
                                )}
                            />
                        }
                    />
                </SectionCard>
            )}
        </>
    );
}

CampaignStats.layout = {
    breadcrumbs: [
        {
            title: msg('newsletter::Newsletter'),
            href: '/admin/plugins/newsletter/campaigns',
        },
    ],
};
