import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { LinkCheckCard } from '@/components/verso/seo/link-check-card';
import { RATING_LABELS, ScoreRing } from '@/components/verso/seo/score-ring';
import { affectedText, SEVERITY_TONES } from '@/components/verso/seo/types';
import type {
    LinkCheckState,
    SeoCheckRow,
    SeoSeverity,
} from '@/components/verso/seo/types';
import {
    EmptyState,
    PageHeader,
    SectionCard,
    SiteDate,
    StatCard,
    StatusBadge,
} from '@/components/verso/ui';
import { formatNumber, msg, t, Trans } from '@/lib/i18n';
import type { Rating } from '@/lib/seo-analysis';
import { cn } from '@/lib/utils';
import { index, refresh } from '@/routes/admin/seo';
import { index as notFoundIndex } from '@/routes/admin/seo/not-found';

type Props = {
    score: number;
    thresholds: { good: number; ok: number };
    checkedAt: string;
    checks: SeoCheckRow[];
    ratings: Record<Exclude<Rating, 'none'> | 'none', number>;
    linkCheck: LinkCheckState;
    notFound: {
        total: number;
        hits: number;
        top: { id: number; path: string; hits: number }[];
        enabled: boolean;
    };
    can: { manage: boolean };
};

type Filter = 'issues' | SeoSeverity | 'passed' | 'all';

const FILTERS: { key: Filter; label: string }[] = [
    { key: 'issues', label: msg('To fix') },
    { key: 'critical', label: msg('Important') },
    { key: 'warning', label: msg('Should fix') },
    { key: 'notice', label: msg('Good to fix') },
    { key: 'passed', label: msg('Passed') },
    { key: 'all', label: msg('All') },
];

const LIGHTS: { key: Rating; label: string; className: string }[] = [
    { key: 'good', label: RATING_LABELS.good, className: 'bg-success-text' },
    { key: 'ok', label: RATING_LABELS.ok, className: 'bg-warning-text' },
    { key: 'poor', label: RATING_LABELS.poor, className: 'bg-danger-text' },
    { key: 'none', label: RATING_LABELS.none, className: 'bg-neutral-text/40' },
];

function ratingOf(score: number, thresholds: Props['thresholds']): Rating {
    if (score >= thresholds.good) {
        return 'good';
    }

    return score >= thresholds.ok ? 'ok' : 'poor';
}

function matches(check: SeoCheckRow, filter: Filter): boolean {
    const hasIssue = check.affected > 0;

    switch (filter) {
        case 'all':
            return true;
        case 'issues':
            return hasIssue;
        case 'passed':
            return !hasIssue;
        default:
            return hasIssue && check.severity === filter;
    }
}

// The SEO overview: one score for the site, what to fix first and how.
export default function SeoOverview(props: Props) {
    const { checks, can } = props;
    const [filter, setFilter] = useState<Filter>('issues');
    const rating = ratingOf(props.score, props.thresholds);
    const open = checks.filter((check) => check.affected > 0);
    const shown = checks.filter((check) => matches(check, filter));
    const count = (severity: SeoSeverity) =>
        open.filter((check) => check.severity === severity).length;

    return (
        <>
            <Head title={t('SEO overview')} />
            <PageHeader
                icon="seo"
                title={t('SEO overview')}
                description={t(
                    'How ready your site is for search engines, and what to fix first.',
                )}
                actions={
                    can.manage && (
                        <Button
                            type="button"
                            variant="outline"
                            className="min-h-10 md:min-h-9"
                            onClick={() =>
                                router.post(
                                    refresh.url(),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            {t('Count again')}
                        </Button>
                    )
                }
            />

            <div className="grid gap-4 @4xl/page:grid-cols-[minmax(17rem,24rem)_minmax(0,1fr)]">
                <SectionCard contentClassName="grid justify-items-center gap-3 text-center">
                    <ScoreRing
                        size="lg"
                        score={props.score}
                        rating={rating}
                        label={t('SEO score of the site')}
                    />
                    <div>
                        <p className="text-base font-semibold text-foreground">
                            {rating === 'good'
                                ? t('Your site is in good shape')
                                : rating === 'ok'
                                  ? t('A few things to improve')
                                  : t('Some important things to fix')}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            <Trans
                                text={t('Counted <date></date>')}
                                components={{
                                    date: () => (
                                        <SiteDate iso={props.checkedAt} />
                                    ),
                                }}
                            />
                        </p>
                    </div>
                </SectionCard>

                <div className="grid min-w-0 content-start gap-4">
                    <div className="auto-fit-grid gap-4 [--grid-min:9rem]">
                        <StatCard
                            label={t('Important')}
                            value={count('critical')}
                            icon="warning"
                        />
                        <StatCard
                            label={t('Should fix')}
                            value={count('warning')}
                        />
                        <StatCard
                            label={t('Good to fix')}
                            value={count('notice')}
                        />
                        <StatCard
                            label={t('Passed')}
                            value={checks.length - open.length}
                            hint={t(
                                'of {count, plural, one {# check} other {# checks}}',
                                { count: checks.length },
                            )}
                        />
                    </div>
                    <ContentLights ratings={props.ratings} />
                </div>
            </div>

            <section
                className="mt-6 grid gap-3"
                aria-labelledby="seo-issues-heading"
            >
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h2
                        id="seo-issues-heading"
                        className="text-base font-semibold text-foreground"
                    >
                        {t('Checks')}
                    </h2>
                    <div
                        className="flex flex-wrap gap-1"
                        role="group"
                        aria-label={t('Show checks')}
                    >
                        {FILTERS.map((item) => (
                            <Button
                                key={item.key}
                                type="button"
                                size="sm"
                                variant={
                                    filter === item.key ? 'secondary' : 'ghost'
                                }
                                aria-pressed={filter === item.key}
                                onClick={() => setFilter(item.key)}
                                className="min-h-10 md:min-h-8"
                            >
                                {t(item.label)}
                                <span className="text-muted-foreground tabular-nums">
                                    {
                                        checks.filter((check) =>
                                            matches(check, item.key),
                                        ).length
                                    }
                                </span>
                            </Button>
                        ))}
                    </div>
                </div>

                {shown.length === 0 ? (
                    <EmptyState
                        icon="seo"
                        title={
                            filter === 'issues'
                                ? t('Nothing to fix. Well done.')
                                : t('No checks here.')
                        }
                    />
                ) : (
                    <ul
                        className="auto-grid gap-4 [--grid-min:24rem]"
                        role="list"
                    >
                        {shown.map((check) => (
                            <li key={check.key} className="min-w-0">
                                <CheckCard check={check} />
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <div className="mt-6 auto-fit-grid gap-4 [--grid-min:24rem]">
                <LinkCheckCard state={props.linkCheck} canManage={can.manage} />
                <SectionCard
                    title={t('Missing pages (404)')}
                    icon="warning"
                    description={
                        props.notFound.enabled
                            ? t(
                                  '{addresses, plural, one {# address} other {# addresses}}, {visits, plural, one {# visit} other {# visits}}.',
                                  {
                                      addresses: props.notFound.total,
                                      visits: props.notFound.hits,
                                  },
                              )
                            : t('The 404 monitor is off in System settings.')
                    }
                    actions={
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                            className="min-h-10 md:min-h-8"
                        >
                            <Link href={notFoundIndex.url()}>
                                {t('Open the 404 monitor')}
                            </Link>
                        </Button>
                    }
                >
                    {props.notFound.top.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t('No missing pages so far.')}
                        </p>
                    ) : (
                        <ul className="grid gap-1.5 text-sm" role="list">
                            {props.notFound.top.map((row) => (
                                <li
                                    key={row.id}
                                    className="flex min-w-0 items-center justify-between gap-3"
                                >
                                    <span className="truncate font-mono text-xs">
                                        {row.path}
                                    </span>
                                    <span className="shrink-0 text-muted-foreground tabular-nums">
                                        {t(
                                            '{count, plural, one {# visit} other {# visits}}',
                                            { count: row.hits },
                                        )}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </SectionCard>
            </div>
        </>
    );
}

// One check: what is wrong, why it matters, how many and how to fix it.
function CheckCard({ check }: { check: SeoCheckRow }) {
    const hasIssue = check.affected > 0;

    return (
        <article
            className="flex h-full min-w-0 flex-col gap-3 rounded-lg border bg-card p-4 shadow-xs"
            data-check={check.key}
        >
            <div className="flex flex-wrap items-center gap-2">
                {check.pending ? (
                    <StatusBadge tone="neutral">
                        {t('Waiting for data')}
                    </StatusBadge>
                ) : hasIssue ? (
                    <StatusBadge tone={SEVERITY_TONES[check.severity]}>
                        {check.severityLabel}
                    </StatusBadge>
                ) : (
                    <StatusBadge tone="success">{t('Passed')}</StatusBadge>
                )}
                <span
                    className={cn(
                        'ms-auto text-sm tabular-nums',
                        hasIssue
                            ? 'font-semibold text-foreground'
                            : 'text-muted-foreground',
                    )}
                >
                    {affectedText(check)}
                </span>
            </div>
            <div className="min-w-0">
                <h3 className="text-sm font-semibold text-foreground">
                    {check.label}
                </h3>
                <p className="mt-1 text-sm text-muted-foreground">
                    {check.description}
                </p>
            </div>
            {hasIssue && (
                <div className="mt-auto flex flex-wrap gap-2">
                    {check.fixUrl && check.fix && (
                        <Button
                            type="button"
                            size="sm"
                            className="min-h-10 md:min-h-8"
                            onClick={() =>
                                router.post(
                                    check.fixUrl as string,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            {check.fix.label}
                        </Button>
                    )}
                    {check.hasItems && (
                        <Button
                            asChild
                            size="sm"
                            variant={check.fixUrl ? 'outline' : 'default'}
                            className="min-h-10 md:min-h-8"
                        >
                            <Link href={check.href}>{t('See the list')}</Link>
                        </Button>
                    )}
                    {check.fix?.kind === 'link' && check.fix.url && (
                        <Button
                            asChild
                            size="sm"
                            variant="outline"
                            className="min-h-10 md:min-h-8"
                        >
                            <Link href={check.fix.url}>{check.fix.label}</Link>
                        </Button>
                    )}
                </div>
            )}
        </article>
    );
}

// How many published entries have a green, amber or red light from the editor checks.
function ContentLights({ ratings }: { ratings: Props['ratings'] }) {
    const total = LIGHTS.reduce((sum, light) => sum + ratings[light.key], 0);

    return (
        <SectionCard
            title={t('Content checks')}
            description={t(
                'The light each published entry got in the editor SEO card.',
            )}
            contentClassName="grid gap-3"
        >
            {total === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('No published content yet.')}
                </p>
            ) : (
                <>
                    <div
                        className="flex h-3 w-full overflow-hidden rounded-full bg-muted"
                        aria-hidden="true"
                    >
                        {LIGHTS.map((light) =>
                            ratings[light.key] > 0 ? (
                                <span
                                    key={light.key}
                                    className={light.className}
                                    style={{
                                        width: `${(ratings[light.key] / total) * 100}%`,
                                    }}
                                />
                            ) : null,
                        )}
                    </div>
                    <ul
                        className="flex flex-wrap gap-x-5 gap-y-1 text-sm"
                        role="list"
                    >
                        {LIGHTS.map((light) => (
                            <li
                                key={light.key}
                                className="flex items-center gap-1.5"
                            >
                                <span
                                    className={cn(
                                        'size-2.5 rounded-full',
                                        light.className,
                                    )}
                                    aria-hidden="true"
                                />
                                {t(light.label)}
                                <span className="font-semibold tabular-nums">
                                    {formatNumber(ratings[light.key])}
                                </span>
                            </li>
                        ))}
                    </ul>
                </>
            )}
        </SectionCard>
    );
}

SeoOverview.layout = {
    breadcrumbs: [{ title: msg('SEO overview'), href: index() }],
};
