import { DashboardTabs } from '@/components/verso/dashboard/dashboard-tabs';
import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { DeviceBreakdown } from '@/components/verso/analytics/device-breakdown';
import { formatDay } from '@/components/verso/analytics/format';
import { RankedList } from '@/components/verso/analytics/ranked-list';
import {
    TrafficChart,
    TrafficLegend,
} from '@/components/verso/analytics/traffic-chart';
import { TrafficTable } from '@/components/verso/analytics/traffic-table';
import type {
    AnalyticsReport,
    AnalyticsSettings,
    RoleOption,
} from '@/components/verso/analytics/types';
import {
    FilterTabs,
    PageHeader,
    SectionCard,
    StatCard,
} from '@/components/verso/ui';
import { formatNumber, msg, t } from '@/lib/i18n';
import { dashboard } from '@/routes/admin';

type Props = {
    range: number;
    ranges: { days: number; href: string }[];
    report: AnalyticsReport;
    settings: AnalyticsSettings;
    roleOptions?: RoleOption[];
    retentionLimits: { min: number; max: number };
    settingsUrl: string;
};

const ANALYTICS_URL = '/admin/analytics';

const DAY_WITH_YEAR: Intl.DateTimeFormatOptions = {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
};

// Analytics plugin page: traffic for the chosen range and the privacy settings.
export default function AnalyticsPage({
    range,
    ranges,
    report,
    settings,
    roleOptions = [],
    retentionLimits,
    settingsUrl,
}: Props) {
    const [showTable, setShowTable] = useState(false);
    const viewsPerVisitor =
        report.totals.visitors > 0
            ? report.totals.views / report.totals.visitors
            : 0;

    return (
        <>
            <Head title={t('analytics::Analytics')} />
            <PageHeader
                icon="analytics"
                title={t('analytics::Analytics')}
                description={t(
                    'analytics::Visitors and page views, counted without cookies or personal data.',
                )}
            />

            <DashboardTabs />

            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <FilterTabs
                    label={t('analytics::Date range')}
                    active={String(range)}
                    tabs={ranges.map((option) => ({
                        key: String(option.days),
                        label: t(
                            'analytics::{days, plural, one {Last # day} other {Last # days}}',
                            { days: option.days },
                        ),
                        href: option.href,
                    }))}
                />
                <p className="text-sm text-muted-foreground tabular-nums">
                    {t('analytics::{from} to {to}', {
                        from: formatDay(report.from, DAY_WITH_YEAR),
                        to: formatDay(report.to, DAY_WITH_YEAR),
                    })}
                </p>
            </div>

            <div className="auto-fit-grid gap-3 [--grid-min:10.5rem] sm:gap-4">
                <StatCard
                    label={t('analytics::Visitors')}
                    value={report.totals.visitors}
                    icon="users"
                    hint={t('analytics::Unique per day, added up')}
                />
                <StatCard
                    label={t('analytics::Page views')}
                    value={report.totals.views}
                    icon="pages"
                />
                <StatCard
                    label={t('analytics::Views per visitor')}
                    value={formatNumber(viewsPerVisitor, {
                        minimumFractionDigits: 1,
                        maximumFractionDigits: 1,
                    })}
                    icon="analytics"
                />
            </div>

            <SectionCard
                className="mt-4"
                icon="analytics"
                title={t('analytics::Traffic')}
                description={t('analytics::Visitors and page views per day.')}
                actions={
                    <Button
                        variant="outline"
                        size="sm"
                        className="min-h-10 md:min-h-8"
                        aria-pressed={showTable}
                        onClick={() => setShowTable((value) => !value)}
                    >
                        {showTable
                            ? t('analytics::Show chart')
                            : t('analytics::Show table')}
                    </Button>
                }
            >
                {showTable ? (
                    <TrafficTable points={report.series} />
                ) : (
                    <div className="flex flex-col gap-3">
                        <TrafficLegend />
                        <TrafficChart points={report.series} />
                    </div>
                )}
            </SectionCard>

            <div className="masonry-grid mt-4 [--grid-min:26rem]">
                <SectionCard
                    icon="pages"
                    title={t('analytics::Top pages')}
                    contentClassName="p-0"
                >
                    <RankedList
                        items={report.pages}
                        nameLabel={t('analytics::Page')}
                        emptyIcon="pages"
                        emptyText={t(
                            'analytics::No page views in this range yet.',
                        )}
                    />
                </SectionCard>
                <SectionCard
                    icon="referrers"
                    title={t('analytics::Top referrers')}
                    description={t(
                        'analytics::Other sites that sent visitors here.',
                    )}
                    contentClassName="p-0"
                >
                    <RankedList
                        items={report.referrers}
                        nameLabel={t('analytics::Site')}
                        emptyIcon="referrers"
                        emptyText={t(
                            'analytics::No visits from other sites in this range.',
                        )}
                    />
                </SectionCard>
                <SectionCard
                    icon="devices"
                    title={t('analytics::Devices')}
                    description={t('analytics::Page views by screen size.')}
                >
                    <DeviceBreakdown devices={report.devices} />
                </SectionCard>
                <SettingsCard
                    settings={settings}
                    roles={roleOptions}
                    limits={retentionLimits}
                    url={settingsUrl}
                />
            </div>
        </>
    );
}

// Privacy options: how long raw events are kept and which signed-in roles are not counted.
function SettingsCard({
    settings,
    roles,
    limits,
    url,
}: {
    settings: AnalyticsSettings;
    roles: RoleOption[];
    limits: { min: number; max: number };
    url: string;
}) {
    const form = useForm<AnalyticsSettings>(settings);
    const { data, setData, errors, processing } = form;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(url, { preserveScroll: true });
    };

    const toggleRole = (role: string, ignored: boolean) => {
        const others = data.ignore_roles.filter((value) => value !== role);

        setData('ignore_roles', ignored ? [...others, role] : others);
    };

    return (
        <form onSubmit={submit}>
            <SectionCard
                icon="settings"
                title={t('analytics::Privacy settings')}
                description={t(
                    'analytics::No cookies are set. Do Not Track and Global Privacy Control are always respected.',
                )}
                contentClassName="grid gap-4"
                footer={
                    <Button
                        type="submit"
                        disabled={processing}
                        className="min-h-10 md:min-h-9"
                    >
                        {t('Save settings')}
                    </Button>
                }
            >
                <div className="grid gap-2">
                    <Label htmlFor="retention_days">
                        {t('analytics::Keep raw page views for (days)')}
                    </Label>
                    <Input
                        id="retention_days"
                        type="number"
                        inputMode="numeric"
                        min={limits.min}
                        max={limits.max}
                        value={data.retention_days}
                        onChange={(event) =>
                            setData(
                                'retention_days',
                                Number(event.target.value),
                            )
                        }
                        className="max-w-40 tabular-nums"
                    />
                    <p className="text-sm text-muted-foreground">
                        {t(
                            'analytics::Daily totals are kept after raw page views are deleted.',
                        )}
                    </p>
                    <InputError message={errors.retention_days} />
                </div>
                <fieldset className="grid gap-2">
                    <legend className="text-sm font-medium text-foreground">
                        {t('analytics::Ignore signed-in visits')}
                    </legend>
                    <p className="text-sm text-muted-foreground">
                        {t(
                            'analytics::Visits of people signed in with these roles are not counted.',
                        )}
                    </p>
                    <div className="grid gap-2 sm:grid-cols-2">
                        {roles.map((role) => (
                            <label
                                key={role.value}
                                className="flex min-h-10 cursor-pointer items-center gap-3 rounded-md border px-3 py-2 text-sm transition-colors hover:bg-muted/50"
                            >
                                <Checkbox
                                    checked={data.ignore_roles.includes(
                                        role.value,
                                    )}
                                    onCheckedChange={(value) =>
                                        toggleRole(role.value, value === true)
                                    }
                                    aria-label={t('analytics::Ignore {role}', {
                                        role: role.label,
                                    })}
                                />
                                <span className="font-medium text-foreground">
                                    {role.label}
                                </span>
                            </label>
                        ))}
                    </div>
                    <InputError message={errors.ignore_roles} />
                </fieldset>
            </SectionCard>
        </form>
    );
}

AnalyticsPage.layout = {
    breadcrumbs: [
        { title: msg('Dashboard'), href: dashboard() },
        { title: msg('analytics::Analytics'), href: ANALYTICS_URL },
    ],
};
