import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Field, ToggleRow } from '@/components/verso/site/settings-fields';
import { SlotItemView, useSlotItems } from '@/components/verso/slots';
import type { SlotItem } from '@/components/verso/slots';
import {
    FilterTabs,
    PageHeader,
    SearchableSelect,
    SectionCard,
    StatusBadge,
} from '@/components/verso/ui';
import type { FilterTab } from '@/components/verso/ui';
import { msg, t } from '@/lib/i18n';
import { localeOptions, timezoneOptions } from '@/lib/select-options';
import { edit, update } from '@/routes/admin/site/settings';
import type { SiteSettings, SiteSettingsOptions } from '@/types';

type Props = {
    settings: SiteSettings;
    options: SiteSettingsOptions;
    seoUrl: string;
};

type SettingsForm = Omit<SiteSettings, 'front_page'> & { front_page: string };

const LATEST_POSTS = 'latest';

const GENERAL_TAB = 'general';

// General settings: site identity, reading options and discussion.
export default function SiteSettingsPage({ settings, options, seoUrl }: Props) {
    const form = useForm<SettingsForm>({
        ...settings,
        front_page: settings.front_page
            ? String(settings.front_page)
            : LATEST_POSTS,
    });
    const { data, setData, errors, processing } = form;
    const tabs = useSettingsTabs();
    const locales = useMemo(
        () => localeOptions(options.locales),
        [options.locales],
    );
    const pages = useMemo(
        () => [
            { value: LATEST_POSTS, label: t('Latest posts') },
            ...options.pages.map((page) => ({
                value: String(page.id),
                label: page.title,
            })),
        ],
        [options.pages],
    );
    const timezones = useMemo(
        () => timezoneOptions(options.timezones),
        [options.timezones],
    );

    form.transform((values) => ({
        ...values,
        front_page:
            values.front_page === LATEST_POSTS
                ? null
                : Number(values.front_page),
    }));

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(update.url(), { preserveScroll: true });
    };

    return (
        <>
            <Head title={t('Settings')} />
            <PageHeader
                icon="settings"
                title={t('Settings')}
                description={t('How your site is named, read and found.')}
            />

            {tabs.list.length > 1 && (
                <div className="mb-4">
                    <FilterTabs
                        tabs={tabs.list}
                        active={tabs.active?.key ?? GENERAL_TAB}
                        label={t('Settings sections')}
                    />
                </div>
            )}

            {tabs.active ? (
                <SlotItemView<'settings-tabs'>
                    item={tabs.active}
                    context={{}}
                />
            ) : (
                <form onSubmit={submit} className="section-grid">
                    <SectionCard
                        icon="site"
                        title={t('General')}
                        description={t('The name and address of your site.')}
                        contentClassName="grid gap-4 md:grid-cols-2"
                    >
                        <Field
                            label={t('Site name')}
                            id="name"
                            error={errors.name}
                        >
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(event) =>
                                    setData('name', event.target.value)
                                }
                                required
                            />
                        </Field>
                        <Field
                            label={t('Tagline')}
                            id="tagline"
                            error={errors.tagline}
                        >
                            <Input
                                id="tagline"
                                value={data.tagline}
                                onChange={(event) =>
                                    setData('tagline', event.target.value)
                                }
                            />
                        </Field>
                        <Field
                            label={t('Site address (URL)')}
                            id="url"
                            error={errors.url}
                            className="md:col-span-2"
                        >
                            <Input
                                id="url"
                                type="url"
                                value={data.url}
                                onChange={(event) =>
                                    setData('url', event.target.value)
                                }
                                required
                            />
                        </Field>
                        <Field
                            label={t('Language')}
                            id="locale"
                            error={errors.locale}
                            hint={t(
                                'The language of your public site. Your admin language is set separately in your profile.',
                            )}
                        >
                            <SearchableSelect
                                id="locale"
                                value={data.locale}
                                onValueChange={(value) =>
                                    setData('locale', value)
                                }
                                options={locales}
                                searchPlaceholder={t('Search languages')}
                                emptyText={t('No language matches.')}
                                aria-invalid={Boolean(errors.locale)}
                                className="w-full"
                            />
                        </Field>
                        <Field
                            label={t('Timezone')}
                            id="timezone"
                            error={errors.timezone}
                        >
                            <SearchableSelect
                                id="timezone"
                                value={data.timezone}
                                onValueChange={(value) =>
                                    setData('timezone', value)
                                }
                                options={timezones}
                                searchPlaceholder={t('Search time zones')}
                                emptyText={t('No time zone matches.')}
                                aria-invalid={Boolean(errors.timezone)}
                                className="w-full"
                            />
                        </Field>
                    </SectionCard>

                    <SectionCard
                        icon="entries"
                        title={t('Reading')}
                        description={t(
                            'What visitors see first and how lists are split.',
                        )}
                        contentClassName="grid gap-4 md:grid-cols-2"
                    >
                        <Field
                            label={t('Home page shows')}
                            id="front_page"
                            error={errors.front_page}
                            className="md:col-span-2"
                        >
                            <SearchableSelect
                                id="front_page"
                                value={data.front_page}
                                onValueChange={(value) =>
                                    setData('front_page', value)
                                }
                                options={pages}
                                searchPlaceholder={t('Search pages')}
                                emptyText={t('No page matches.')}
                                aria-invalid={Boolean(errors.front_page)}
                                className="w-full"
                            />
                        </Field>
                        <Field
                            label={t('Posts per page')}
                            id="posts_per_page"
                            error={errors.posts_per_page}
                        >
                            <Input
                                id="posts_per_page"
                                type="number"
                                min={1}
                                value={data.posts_per_page}
                                onChange={(event) =>
                                    setData(
                                        'posts_per_page',
                                        Number(event.target.value),
                                    )
                                }
                            />
                        </Field>
                        <Field
                            label={t('Revisions to keep per entry')}
                            id="revisions_limit"
                            error={errors.revisions_limit}
                        >
                            <Input
                                id="revisions_limit"
                                type="number"
                                min={0}
                                value={data.revisions_limit}
                                onChange={(event) =>
                                    setData(
                                        'revisions_limit',
                                        Number(event.target.value),
                                    )
                                }
                            />
                        </Field>
                    </SectionCard>

                    <SectionCard
                        icon="comments"
                        title={t('Discussion')}
                        description={t('Let visitors leave comments on posts.')}
                    >
                        <ToggleRow
                            checked={data.comments_enabled}
                            onChange={(checked) =>
                                setData('comments_enabled', checked)
                            }
                            label={t('Allow comments')}
                            hint={t(
                                'When off, no comment list or form is shown on the site.',
                            )}
                        />
                    </SectionCard>

                    <SectionCard
                        icon="seo"
                        title={t('Search engines')}
                        description={t(
                            'URL structure, sitemap, robots.txt and search engine settings.',
                        )}
                    >
                        <p className="text-sm text-muted-foreground">
                            {t('These settings live on their own page.')}{' '}
                            <Link
                                href={seoUrl}
                                className="font-medium text-brand hover:underline"
                            >
                                {t('Open Permalinks and SEO')}
                            </Link>
                        </p>
                    </SectionCard>

                    <div className="sticky bottom-4 z-10 flex flex-wrap items-center justify-end gap-3 rounded-lg border bg-card px-4 py-3 shadow-xs">
                        {form.isDirty && (
                            <StatusBadge tone="warning" className="me-auto">
                                {t('Unsaved changes')}
                            </StatusBadge>
                        )}
                        <Button
                            type="submit"
                            disabled={processing}
                            className="min-h-10 md:min-h-9"
                        >
                            {t('Save settings')}
                        </Button>
                    </div>
                </form>
            )}
        </>
    );
}

// The General tab plus the tabs plugins add. The active one comes from ?tab= in the URL.
function useSettingsTabs(): { list: FilterTab[]; active: SlotItem | null } {
    const items = useSlotItems('settings-tabs');
    const [path, query = ''] = usePage().url.split('?');
    const activeKey = new URLSearchParams(query).get('tab');

    return {
        list: [
            { key: GENERAL_TAB, label: t('General'), href: path },
            ...items.map((item) => ({
                key: item.key,
                label: item.label ?? item.key,
                href: `${path}?tab=${encodeURIComponent(item.key)}`,
            })),
        ],
        active: items.find((item) => item.key === activeKey) ?? null,
    };
}

SiteSettingsPage.layout = {
    breadcrumbs: [{ title: msg('Settings'), href: edit() }],
};
