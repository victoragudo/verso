import { SectionCard, StatusBadge } from '@/components/verso/ui';
import { formatBytes, formatList, formatNumber, msg, t } from '@/lib/i18n';
import type {
    PreviewPage,
    ThemeQualityMarker,
    ThemeReportDetails,
} from '@/types';

// Plain words for each quality marker of a theme.
export const QUALITY_LABELS: Record<ThemeQualityMarker, string> = {
    seo: msg('SEO tags ({tag})'),
    head: msg('Plugin head hook ({tag})'),
    footer: msg('Plugin footer hook ({tag})'),
    skipLink: msg('Skip to content link'),
    lang: msg('Page language (lang attribute)'),
};

const QUALITY_TAGS: Partial<Record<ThemeQualityMarker, string>> = {
    seo: '<x-verso-seo />',
    head: '<x-verso-head />',
    footer: '<x-verso-footer />',
};

// Names of the pages a theme renders in the sandbox.
export const PAGE_LABELS: Record<string, string> = {
    home: msg('Home page'),
    entry: msg('A post'),
    archive: msg('Blog archive'),
};

const PREVIEW_TITLES: Record<string, string> = {
    home: msg('Preview of the home page'),
    entry: msg('Preview of a post'),
    archive: msg('Preview of the blog archive'),
};

export function formatSeconds(seconds: number): string {
    return formatNumber(seconds, {
        style: 'unit',
        unit: 'second',
        unitDisplay: 'short',
    });
}

// Rendered HTML runs with no scripts and may not load anything from the network.
const PREVIEW_POLICY =
    '<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; style-src \'unsafe-inline\'; img-src data:; font-src data:">';

// The HTML of a preview page, with a policy that blocks scripts and requests.
export function previewDocument(html: string): string {
    return `${PREVIEW_POLICY}${html}`;
}

// What the requirement and quality checks found about the theme.
export function ThemeChecksCard({ theme }: { theme: ThemeReportDetails }) {
    return (
        <SectionCard
            title={t('Requirements and quality')}
            description={t(
                'What theme.json declares, and what the views include.',
            )}
        >
            <dl className="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-2 text-sm">
                <dt className="text-muted-foreground">{t('Needs')}</dt>
                <dd>
                    {t('Verso {verso}, PHP {php}', {
                        verso: theme.requires ?? t('not declared'),
                        php: theme.php ?? t('not declared'),
                    })}
                </dd>
                <dt className="text-muted-foreground">{t('Parent')}</dt>
                <dd>{theme.parent ?? t('None')}</dd>
                <dt className="text-muted-foreground">{t('Screenshot')}</dt>
                <dd className="font-mono text-xs break-all">
                    {theme.screenshot ?? t('None')}
                </dd>
                <dt className="text-muted-foreground">{t('Declared hosts')}</dt>
                <dd className="break-all">
                    {theme.network.length === 0
                        ? t('None')
                        : formatList(theme.network, 'unit')}
                </dd>
                <dt className="text-muted-foreground">{t('Hosts in code')}</dt>
                <dd className="break-all">
                    {theme.hosts.length === 0
                        ? t('None')
                        : formatList(theme.hosts, 'unit')}
                </dd>
            </dl>

            <ul
                className="mt-4 grid gap-2 text-sm"
                aria-label={t('Quality checks')}
            >
                {(Object.keys(QUALITY_LABELS) as ThemeQualityMarker[]).map(
                    (marker) => (
                        <li
                            key={marker}
                            className="flex items-center justify-between gap-3"
                        >
                            <span className="min-w-0 break-words text-body">
                                {t(QUALITY_LABELS[marker], {
                                    tag: QUALITY_TAGS[marker],
                                })}
                            </span>
                            <StatusBadge
                                tone={
                                    theme.quality[marker]
                                        ? 'success'
                                        : 'warning'
                                }
                            >
                                {theme.quality[marker]
                                    ? t('Found')
                                    : t('Missing')}
                            </StatusBadge>
                        </li>
                    ),
                )}
            </ul>
        </SectionCard>
    );
}

// The pages the theme rendered with demo content, each in a locked down frame.
export function PreviewPages({ pages }: { pages: PreviewPage[] }) {
    if (pages.length === 0) {
        return null;
    }

    return (
        <SectionCard
            title={t('Preview')}
            description={t(
                'Pages rendered with demo posts in the sandbox. Scripts and outside files are turned off in the preview.',
            )}
        >
            <ul className="auto-fit-grid gap-4 [--grid-min:22rem]">
                {pages.map((page) => (
                    <li
                        key={page.name}
                        className="flex min-w-0 flex-col gap-2 rounded-md border p-3"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <span className="text-sm font-medium text-foreground">
                                {PAGE_LABELS[page.name]
                                    ? t(PAGE_LABELS[page.name])
                                    : page.name}
                            </span>
                            <StatusBadge
                                tone={
                                    page.status === 200 ? 'success' : 'danger'
                                }
                            >
                                {page.status === 200
                                    ? t('Rendered')
                                    : t('Error {status}', {
                                          status: String(page.status),
                                      })}
                            </StatusBadge>
                        </div>
                        <p className="font-mono text-xs break-all text-muted-foreground">
                            {page.path} · {formatBytes(page.bytes)} ·{' '}
                            {formatSeconds(page.seconds)}
                        </p>
                        {page.html === '' ? (
                            <p className="rounded-md bg-muted/50 px-3 py-6 text-center text-sm text-muted-foreground">
                                {t('No preview kept for this page.')}
                            </p>
                        ) : (
                            <iframe
                                title={
                                    PREVIEW_TITLES[page.name]
                                        ? t(PREVIEW_TITLES[page.name])
                                        : t('Preview of {page}', {
                                              page: page.name,
                                          })
                                }
                                srcDoc={previewDocument(page.html)}
                                sandbox=""
                                loading="lazy"
                                referrerPolicy="no-referrer"
                                className="aspect-[4/3] w-full rounded-md border bg-white"
                            />
                        )}
                    </li>
                ))}
            </ul>
        </SectionCard>
    );
}
