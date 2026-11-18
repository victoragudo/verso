import { useForm } from '@inertiajs/react';
import { useMemo, useRef } from 'react';
import type { FormEvent } from 'react';
import { Input } from '@/components/ui/input';
import { Field, ToggleRow } from '@/components/verso/site/settings-fields';
import {
    SearchableSelect,
    SectionCard,
    StatusBadge,
} from '@/components/verso/ui';
import type { SearchableOption } from '@/components/verso/ui';
import { t } from '@/lib/i18n';
import { sitemap as sitemapRoute } from '@/routes/admin/site/permalinks';
import type { PermalinkTaxonomy, PermalinkType, SitemapOptions } from '@/types';
import { focusFirstError, SaveBar } from './save-bar';

const NONE = '';

type Props = {
    values: SitemapOptions;
    types: PermalinkType[];
    taxonomies: PermalinkTaxonomy[];
    entryOptions: SearchableOption[];
    status: Record<string, number>;
    sitemapUrl: string;
};

// Sitemap on or off, what it lists, entries left out, images, file size and dates.
export function SitemapForm({
    values,
    types,
    taxonomies,
    entryOptions,
    status,
    sitemapUrl,
}: Props) {
    const form = useForm<SitemapOptions>(values);
    const { data, setData, errors, processing } = form;
    const formRef = useRef<HTMLFormElement>(null);
    const fieldErrors = errors as Record<string, string | undefined>;
    const excluded = useMemo(
        () => new Set(data.excluded_entries.map(String)),
        [data.excluded_entries],
    );
    const addable = useMemo(
        () => entryOptions.filter((option) => !excluded.has(option.value)),
        [entryOptions, excluded],
    );
    const labels = useMemo(
        () =>
            new Map(entryOptions.map((option) => [option.value, option.label])),
        [entryOptions],
    );
    const total = Object.values(status).reduce((sum, count) => sum + count, 0);

    const toggle = (key: 'types' | 'taxonomies', name: string, on: boolean) =>
        setData(
            key,
            on
                ? [...data[key], name]
                : data[key].filter((value) => value !== name),
        );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(sitemapRoute.url(), {
            preserveScroll: true,
            onError: () => focusFirstError(formRef.current),
        });
    };

    return (
        <form
            ref={formRef}
            onSubmit={submit}
            className="section-grid [--grid-min:36rem]"
        >
            <SectionCard
                icon="seo"
                title={t('Sitemap')}
                description={t(
                    'A list of your public pages for search engines.',
                )}
                actions={
                    data.enabled && values.enabled ? (
                        <a
                            href={sitemapUrl}
                            target="_blank"
                            rel="noreferrer"
                            className="text-sm font-medium text-brand hover:underline"
                        >
                            {t('View sitemap')}
                        </a>
                    ) : null
                }
                contentClassName="grid gap-3"
            >
                <ToggleRow
                    checked={data.enabled}
                    onChange={(checked) => setData('enabled', checked)}
                    label={t('Publish a sitemap')}
                    hint={t('Serves /sitemap.xml and adds it to robots.txt.')}
                />
                <p className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                    <StatusBadge tone={values.enabled ? 'success' : 'neutral'}>
                        {values.enabled ? t('Live') : t('Off')}
                    </StatusBadge>
                    {values.enabled && (
                        <span className="tabular-nums">
                            {t(
                                '{count, plural, one {# address} other {# addresses}} in {files, plural, one {# sitemap} other {# sitemaps}}',
                                {
                                    count: total,
                                    files: Object.keys(status).length,
                                },
                            )}
                        </span>
                    )}
                </p>
            </SectionCard>

            <SectionCard
                icon="entries"
                title={t('What to include')}
                description={t(
                    'Entries hidden from search engines are never listed.',
                )}
                contentClassName="grid gap-4 md:grid-cols-2"
            >
                <fieldset className="grid content-start gap-2">
                    <legend className="mb-1 text-sm font-medium">
                        {t('Content types')}
                    </legend>
                    {types.map((type) => (
                        <ToggleRow
                            key={type.name}
                            checked={data.types.includes(type.name)}
                            onChange={(on) => toggle('types', type.name, on)}
                            label={type.pluralLabel}
                            hint={t('{count, number} published and listed', {
                                count: status[type.name] ?? 0,
                            })}
                        />
                    ))}
                </fieldset>
                <fieldset className="grid content-start gap-2">
                    <legend className="mb-1 text-sm font-medium">
                        {t('Taxonomies')}
                    </legend>
                    {taxonomies.map((taxonomy) => (
                        <ToggleRow
                            key={taxonomy.name}
                            checked={data.taxonomies.includes(taxonomy.name)}
                            onChange={(on) =>
                                toggle('taxonomies', taxonomy.name, on)
                            }
                            label={taxonomy.label}
                            hint={t(
                                'Archive pages of terms with published entries',
                            )}
                        />
                    ))}
                </fieldset>
                <Field
                    label={t('Leave out single entries')}
                    id="excluded-entry"
                    error={fieldErrors.excluded_entries}
                    hint={t(
                        'Editors can also do this in the SEO panel of an entry.',
                    )}
                    className="md:col-span-2"
                >
                    <SearchableSelect
                        id="excluded-entry"
                        value={NONE}
                        onValueChange={(value) =>
                            value !== NONE &&
                            setData('excluded_entries', [
                                ...data.excluded_entries,
                                Number(value),
                            ])
                        }
                        options={addable}
                        placeholder={t('Pick an entry to leave out')}
                        searchPlaceholder={t('Search entries')}
                        emptyText={t('No entry matches.')}
                        className="w-full"
                    />
                    {data.excluded_entries.length > 0 && (
                        <ul
                            className="flex flex-wrap gap-2"
                            aria-label={t('Entries left out')}
                        >
                            {data.excluded_entries.map((id) => (
                                <li
                                    key={id}
                                    className="flex items-center gap-1 rounded-md border bg-muted/40 py-0.5 ps-2 pe-0.5 text-sm"
                                >
                                    {labels.get(String(id)) ??
                                        t('Entry {id}', { id })}
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setData(
                                                'excluded_entries',
                                                data.excluded_entries.filter(
                                                    (value) => value !== id,
                                                ),
                                            )
                                        }
                                        aria-label={t('Include {entry} again', {
                                            entry:
                                                labels.get(String(id)) ??
                                                t('entry {id}', { id }),
                                        })}
                                        className="flex size-7 items-center justify-center rounded text-muted-foreground hover:bg-muted hover:text-foreground"
                                    >
                                        <span aria-hidden="true">×</span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </Field>
            </SectionCard>

            <SectionCard
                icon="settings"
                title={t('Details')}
                contentClassName="grid gap-4 md:grid-cols-2"
            >
                <ToggleRow
                    checked={data.include_images}
                    onChange={(checked) => setData('include_images', checked)}
                    label={t('Include featured images')}
                    hint={t('Helps images show up in image search.')}
                />
                <div />
                <Field
                    label={t('Addresses per sitemap file')}
                    id="max_urls"
                    error={fieldErrors.max_urls}
                    hint={t('Bigger lists are split into more files.')}
                >
                    <Input
                        id="max_urls"
                        type="number"
                        min={1}
                        inputMode="numeric"
                        value={data.max_urls}
                        onChange={(event) =>
                            setData('max_urls', Number(event.target.value))
                        }
                        className="tabular-nums"
                    />
                </Field>
                <Field
                    label={t('Last modified date')}
                    id="last_modified"
                    error={fieldErrors.last_modified}
                >
                    <SearchableSelect
                        id="last_modified"
                        value={data.last_modified}
                        onValueChange={(value) =>
                            setData(
                                'last_modified',
                                value as SitemapOptions['last_modified'],
                            )
                        }
                        options={[
                            { value: 'updated', label: t('Last edit') },
                            { value: 'published', label: t('Publish date') },
                        ]}
                        className="w-full"
                    />
                </Field>
            </SectionCard>

            <SaveBar
                dirty={form.isDirty}
                processing={processing}
                label={t('Save sitemap settings')}
            />
        </form>
    );
}
