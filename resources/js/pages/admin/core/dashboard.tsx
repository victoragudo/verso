import { DashboardTabs } from '@/components/verso/dashboard/dashboard-tabs';
import { Head, Link, usePage } from '@inertiajs/react';
import { Slot, useSlotItems } from '@/components/verso/slots';
import {
    EmptyState,
    NoticeBanner,
    PageHeader,
    SectionCard,
    StatCard,
    StatusBadge,
    statusTone,
} from '@/components/verso/ui';
import { simplePanels, simpleStats } from '@/lib/easy/simple-dashboard';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes/admin';
import type { DashboardNotice, DashboardPanel, DashboardStat } from '@/types';

type Props = {
    stats: DashboardStat[];
    panels: DashboardPanel[];
    notices?: DashboardNotice[];
};

// Admin home: counts from every module and short lists to act on.
// In simple mode only the parts that lead to pages in the menu stay, with plain names.
export default function Dashboard({
    stats: allStats,
    panels: allPanels,
    notices = [],
}: Props) {
    const { auth, siteName, simpleMode, versoNav } = usePage().props;
    const stats = simpleMode ? simpleStats(allStats, versoNav) : allStats;
    const panels = simpleMode ? simplePanels(allPanels, versoNav) : allPanels;
    const topCards = useSlotItems('dashboard-top');
    const firstName = auth.user?.name.split(' ')[0];
    const widgets = useSlotItems('dashboard-widgets');

    return (
        <>
            <Head title={t('Dashboard')} />
            <PageHeader
                icon="dashboard"
                title={
                    firstName
                        ? t('Welcome back, {name}', { name: firstName })
                        : t('Dashboard')
                }
                description={t('Here is what is going on at {site}.', {
                    site: siteName,
                })}
            />

            <DashboardTabs />

            {notices.length > 0 && (
                <div className="mb-6 grid gap-3">
                    {notices.map((notice) => (
                        <NoticeBanner
                            key={notice.key}
                            title={notice.title}
                            description={notice.description}
                            tone={notice.tone}
                            icon={notice.icon ?? undefined}
                            href={notice.href}
                            action={notice.action}
                        />
                    ))}
                </div>
            )}

            <Slot name="dashboard-top" context={{}} />

            {stats.length > 0 && (
                // Stats sit in one row when there is room, and wrap on small screens.
                <div className="auto-fit-grid gap-3 [--grid-min:10.5rem] sm:gap-4">
                    {stats.map((stat) => (
                        <StatCard
                            key={stat.key}
                            label={stat.label}
                            value={stat.value}
                            href={stat.href}
                            icon={stat.icon ?? undefined}
                        />
                    ))}
                </div>
            )}

            {panels.length > 0 && (
                <div className="masonry-grid mt-6">
                    {panels.map((panel) => (
                        <PanelCard key={panel.key} panel={panel} />
                    ))}
                </div>
            )}

            {widgets.length > 0 && (
                <div className="masonry-grid mt-6">
                    <Slot name="dashboard-widgets" context={{}} />
                </div>
            )}

            {stats.length === 0 &&
                panels.length === 0 &&
                widgets.length === 0 &&
                topCards.length === 0 && (
                    <SectionCard>
                        <EmptyState
                            icon="dashboard"
                            title={t('Nothing to show yet')}
                            description={t(
                                'Numbers and lists from your site will appear here.',
                            )}
                        />
                    </SectionCard>
                )}
        </>
    );
}

// One dashboard list, like recent entries or pending comments.
function PanelCard({ panel }: { panel: DashboardPanel }) {
    return (
        <SectionCard
            title={panel.label}
            icon={panel.icon}
            contentClassName="p-0"
            actions={
                panel.href && (
                    <Link
                        href={panel.href}
                        className="inline-flex min-h-6 items-center text-sm font-medium text-brand hover:underline pointer-coarse:min-h-11"
                    >
                        {t('View all')}
                    </Link>
                )
            }
        >
            {panel.items.length === 0 ? (
                <EmptyState
                    icon={panel.icon}
                    title={panel.emptyText}
                    className="py-10"
                />
            ) : (
                <ul className="divide-y">
                    {panel.items.map((item) => (
                        <li key={item.key}>
                            <PanelRow item={item} />
                        </li>
                    ))}
                </ul>
            )}
        </SectionCard>
    );
}

function PanelRow({ item }: { item: DashboardPanel['items'][number] }) {
    const body = (
        <>
            <span className="min-w-0 flex-1">
                <span className="block truncate text-sm font-medium text-foreground">
                    <bdi>{item.title}</bdi>
                </span>
                {item.meta && (
                    <span className="mt-0.5 block truncate text-xs text-muted-foreground">
                        {item.meta}
                    </span>
                )}
            </span>
            {item.status && (
                <StatusBadge
                    tone={statusTone(item.status)}
                    className="capitalize"
                >
                    {t(
                        '{status, select, draft {Draft} scheduled {Scheduled} published {Published} pending {Pending} approved {Approved} spam {Spam} trash {Trash} other {{status}}}',
                        { status: item.status },
                    )}
                </StatusBadge>
            )}
        </>
    );
    const className = 'flex min-h-14 items-center gap-3 px-4 py-2.5 sm:px-5';

    return item.href ? (
        <Link
            href={item.href}
            className={`${className} transition-colors hover:bg-muted/50`}
        >
            {body}
        </Link>
    ) : (
        <div className={className}>{body}</div>
    );
}

Dashboard.layout = () => ({
    breadcrumbs: [
        {
            title: t('Dashboard'),
            href: dashboard(),
        },
    ],
});
