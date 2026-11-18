import { Head, usePage } from '@inertiajs/react';
import { PermalinksForm } from '@/components/verso/site/permalinks/permalinks-form';
import { SearchEnginesForm } from '@/components/verso/site/permalinks/search-engines-form';
import { SitemapForm } from '@/components/verso/site/permalinks/sitemap-form';
import { FilterTabs, PageHeader } from '@/components/verso/ui';
import type { SearchableOption } from '@/components/verso/ui';
import { msg, t } from '@/lib/i18n';
import { edit } from '@/routes/admin/site/permalinks';
import type {
    PermalinkTaxonomy,
    PermalinkType,
    PermalinkValues,
    SearchEngineSettings,
    SitemapOptions,
} from '@/types';

type Props = {
    permalinks: PermalinkValues;
    types: PermalinkType[];
    taxonomies: PermalinkTaxonomy[];
    presets: Record<string, string>;
    tokens: string[];
    trailingSlashNotice: boolean;
    sitemap: SitemapOptions;
    sitemapStatus: Record<string, number>;
    entryOptions: SearchableOption[];
    search: SearchEngineSettings;
    links: { sitemap: string; robots: string; site: string };
    robotsDefault: string;
    site: { name: string; tagline: string };
};

const TABS = [
    { key: 'permalinks', label: msg('URL structure') },
    { key: 'sitemap', label: msg('Sitemap') },
    { key: 'search', label: msg('Search engines') },
];

// "Permalinks and SEO": how addresses are built, the sitemap and search engine settings.
export default function PermalinksPage(props: Props) {
    const [path, query = ''] = usePage().url.split('?');
    const requested = new URLSearchParams(query).get('tab');
    const active = TABS.some((tab) => tab.key === requested)
        ? (requested as string)
        : 'permalinks';

    return (
        <>
            <Head title={t('Permalinks and SEO')} />
            <PageHeader
                icon="seo"
                title={t('Permalinks and SEO')}
                description={t(
                    'How addresses look, what search engines find and how pages are shown in results.',
                )}
            />

            <div className="mb-4">
                <FilterTabs
                    tabs={TABS.map((tab) => ({
                        key: tab.key,
                        label: t(tab.label),
                        href: `${path}?tab=${tab.key}`,
                    }))}
                    active={active}
                    label={t('Permalinks and SEO sections')}
                />
            </div>

            {active === 'permalinks' && (
                <PermalinksForm
                    values={props.permalinks}
                    types={props.types}
                    taxonomies={props.taxonomies}
                    presets={props.presets}
                    tokens={props.tokens}
                    siteUrl={props.links.site}
                    trailingSlashNotice={props.trailingSlashNotice}
                />
            )}
            {active === 'sitemap' && (
                <SitemapForm
                    values={props.sitemap}
                    types={props.types}
                    taxonomies={props.taxonomies}
                    entryOptions={props.entryOptions}
                    status={props.sitemapStatus}
                    sitemapUrl={props.links.sitemap}
                />
            )}
            {active === 'search' && (
                <SearchEnginesForm
                    values={props.search}
                    robotsDefault={props.robotsDefault}
                    robotsUrl={props.links.robots}
                    siteUrl={props.links.site}
                    site={props.site}
                />
            )}
        </>
    );
}

PermalinksPage.layout = {
    breadcrumbs: [{ title: msg('Permalinks and SEO'), href: edit() }],
};
