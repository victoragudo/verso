import { Head, Link, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Pagination } from '@/components/verso/content/pagination';
import {
    DataTable,
    EmptyState,
    PageHeader,
    SiteDate,
    StatusBadge,
    statusTone,
} from '@/components/verso/ui';
import type { DataTableColumn } from '@/components/verso/ui';
import { formatNumber, msg, t } from '@/lib/i18n';
import type { Paginated } from '@/types';
import { QueueNotice } from '@/components/verso/newsletter/queue-notice';
import type { QueueNoticeData } from '@/components/verso/newsletter/queue-notice';

type CampaignRow = {
    id: number;
    subject: string;
    status: 'draft' | 'sending' | 'sent';
    list: string | null;
    recipients: number;
    sent_at: string | null;
    updated_at: string | null;
};

type Props = {
    queueNotice?: QueueNoticeData | null;
    campaigns: Paginated<CampaignRow>;
    urls: {
        create: string;
        campaign: string;
        subscribers: string;
        settings: string;
    };
};

const STATUS_TONES: Record<CampaignRow['status'], string> = {
    draft: 'draft',
    sending: 'running',
    sent: 'completed',
};

const STATUS_LABELS: Record<CampaignRow['status'], string> = {
    draft: msg('Draft'),
    sending: msg('newsletter::Sending'),
    sent: msg('newsletter::Sent'),
};

// Lists newsletter campaigns: drafts, the ones being sent and the sent ones.
export default function CampaignsIndex({
    campaigns,
    urls,
    queueNotice = null,
}: Props) {
    const campaignUrl = (row: CampaignRow) => {
        const url = urls.campaign.replace('__ID__', String(row.id));

        return row.status === 'draft' ? `${url}/edit` : url;
    };

    const columns: DataTableColumn<CampaignRow>[] = [
        {
            key: 'subject',
            header: t('newsletter::Subject'),
            primary: true,
            className: 'max-w-0 w-full',
            cell: (row) => (
                <Link
                    href={campaignUrl(row)}
                    className="block truncate font-medium text-foreground hover:underline"
                >
                    {row.subject}
                </Link>
            ),
        },
        {
            key: 'status',
            header: t('Status'),
            cell: (row) => (
                <StatusBadge tone={statusTone(STATUS_TONES[row.status])}>
                    {t(STATUS_LABELS[row.status])}
                </StatusBadge>
            ),
        },
        {
            key: 'list',
            header: t('newsletter::List'),
            className: 'whitespace-nowrap text-muted-foreground',
            cell: (row) => row.list ?? t('newsletter::Everyone'),
        },
        {
            key: 'recipients',
            header: t('newsletter::Recipients'),
            className: 'tabular-nums md:text-end',
            cell: (row) =>
                row.status === 'draft' ? '-' : formatNumber(row.recipients),
        },
        {
            key: 'date',
            header: t('Date'),
            className: 'whitespace-nowrap text-muted-foreground',
            cell: (row) => <SiteDate iso={row.sent_at ?? row.updated_at} />,
        },
    ];

    return (
        <>
            <Head title={t('newsletter::Newsletter')} />
            <PageHeader
                icon="newsletter"
                title={t('newsletter::Newsletter')}
                description={t(
                    'newsletter::Write campaigns with blocks and send them to your subscribers.',
                )}
                actions={
                    <>
                        <Button asChild variant="outline">
                            <Link href={urls.subscribers}>
                                {t('newsletter::Subscribers')}
                            </Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href={urls.settings}>{t('Settings')}</Link>
                        </Button>
                        <Button asChild>
                            <Link href={urls.create}>
                                {t('newsletter::New campaign')}
                            </Link>
                        </Button>
                    </>
                }
            />
            <QueueNotice notice={queueNotice} />
            <DataTable
                rows={campaigns.data}
                columns={columns}
                rowKey={(row) => row.id}
                caption={t('newsletter::Campaigns')}
                rowLabel={(row) => row.subject}
                rowActions={(row) => [
                    {
                        label:
                            row.status === 'draft'
                                ? t('Edit')
                                : t('newsletter::View stats'),
                        onSelect: () => router.visit(campaignUrl(row)),
                    },
                ]}
                empty={
                    <EmptyState
                        icon="newsletter"
                        title={t('newsletter::No campaigns yet.')}
                        action={
                            <Button asChild size="sm">
                                <Link href={urls.create}>
                                    {t('newsletter::Write the first one')}
                                </Link>
                            </Button>
                        }
                    />
                }
            />
            <Pagination page={campaigns} />
        </>
    );
}

CampaignsIndex.layout = {
    breadcrumbs: [
        {
            title: msg('newsletter::Newsletter'),
            href: '/admin/plugins/newsletter/campaigns',
        },
    ],
};
