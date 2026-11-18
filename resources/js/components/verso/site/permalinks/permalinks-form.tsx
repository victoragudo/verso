import { useForm } from '@inertiajs/react';
import { useMemo, useRef } from 'react';
import type { FormEvent } from 'react';
import { Input } from '@/components/ui/input';
import { Field, ToggleRow } from '@/components/verso/site/settings-fields';
import {
    NoticeBanner,
    SearchableSelect,
    SectionCard,
} from '@/components/verso/ui';
import { formatList, msg, t } from '@/lib/i18n';
import {
    buildPermalink,
    patternProblem,
    presetFor,
    withTrailingSlash,
} from '@/lib/permalinks';
import { update } from '@/routes/admin/site/permalinks';
import type {
    PermalinkTaxonomy,
    PermalinkType,
    PermalinkValues,
} from '@/types';
import { focusFirstError, SaveBar } from './save-bar';

const CUSTOM = 'custom';

const PRESET_LABELS: Record<string, string> = {
    plain: msg('Post name'),
    blog: msg('Base and name'),
    month: msg('Month and name'),
    day: msg('Day and name'),
    category: msg('Category and name'),
    numeric: msg('Numeric'),
};

type Props = {
    values: PermalinkValues;
    types: PermalinkType[];
    taxonomies: PermalinkTaxonomy[];
    presets: Record<string, string>;
    tokens: string[];
    siteUrl: string;
    trailingSlashNotice: boolean;
};

// URL structures per content type, taxonomy bases and the URL options, with a live preview.
export function PermalinksForm({
    values,
    types,
    taxonomies,
    presets,
    tokens,
    siteUrl,
    trailingSlashNotice,
}: Props) {
    const form = useForm<PermalinkValues>(values);
    const { data, setData, errors, processing } = form;
    const formRef = useRef<HTMLFormElement>(null);
    const fieldErrors = errors as Record<string, string | undefined>;
    const presetOptions = useMemo(
        () => [
            ...Object.entries(presets).map(([key, pattern]) => ({
                value: key,
                label: `${PRESET_LABELS[key] ? t(PRESET_LABELS[key]) : key}  ${pattern}`,
            })),
            { value: CUSTOM, label: t('Custom structure') },
        ],
        [presets],
    );
    const origin = siteUrl.replace(/\/$/, '');

    const setStructure = (type: string, pattern: string) =>
        setData('structures', { ...data.structures, [type]: pattern });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(update.url(), {
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
            {types.map((type) => {
                const pattern =
                    data.structures[type.name] ?? type.defaultStructure;
                const id = `structure-${type.name}`;
                const problem = patternProblem(
                    pattern,
                    type.hierarchical,
                    tokens,
                );
                const archiveBase = data.archive_bases[type.name] ?? '';

                return (
                    <SectionCard
                        key={type.name}
                        icon={type.hierarchical ? 'pages' : 'posts'}
                        title={type.pluralLabel}
                        description={
                            type.hierarchical
                                ? t(
                                      'Nested entries put their parents in {token}, like about/team.',
                                      { token: '{slug}' },
                                  )
                                : t('How the address of one {type} is built.', {
                                      type: type.inlineLabel,
                                  })
                        }
                        contentClassName="grid gap-4 md:grid-cols-2"
                    >
                        <Field label={t('Structure')} id={`${id}-preset`}>
                            <SearchableSelect
                                id={`${id}-preset`}
                                value={presetFor(pattern, presets)}
                                onValueChange={(key) =>
                                    key !== CUSTOM &&
                                    setStructure(type.name, presets[key])
                                }
                                options={presetOptions}
                                searchPlaceholder={t('Search structures')}
                                emptyText={t('No structure matches.')}
                                className="w-full"
                            />
                        </Field>
                        <Field
                            label={t('Pattern')}
                            id={id}
                            error={
                                fieldErrors[`structures.${type.name}`] ??
                                problem ??
                                undefined
                            }
                            hint={
                                <TokenList
                                    tokens={tokens}
                                    onPick={(token) =>
                                        setStructure(
                                            type.name,
                                            `${pattern.replace(/\/$/, '')}/{${token}}`,
                                        )
                                    }
                                />
                            }
                        >
                            <Input
                                id={id}
                                value={pattern}
                                onChange={(event) =>
                                    setStructure(type.name, event.target.value)
                                }
                                spellCheck={false}
                                autoCapitalize="none"
                                autoComplete="off"
                                className="font-mono text-sm"
                            />
                        </Field>
                        <Preview
                            className="md:col-span-2"
                            label={t('Example')}
                            url={`${origin}${buildPermalink(pattern, type.sample, data.trailing_slash)}`}
                        />
                        {type.hasArchive && (
                            <Field
                                label={t('Archive base')}
                                id={`archive-${type.name}`}
                                error={
                                    fieldErrors[`archive_bases.${type.name}`]
                                }
                                hint={
                                    archiveBase === ''
                                        ? t('Empty turns the archive page off.')
                                        : t('The list of all {types}: {url}', {
                                              types: type.inlinePluralLabel,
                                              url: `${origin}${withTrailingSlash(`/${archiveBase}`, data.trailing_slash)}`,
                                          })
                                }
                                className="md:col-span-2"
                            >
                                <Input
                                    id={`archive-${type.name}`}
                                    value={archiveBase}
                                    onChange={(event) =>
                                        setData('archive_bases', {
                                            ...data.archive_bases,
                                            [type.name]: event.target.value,
                                        })
                                    }
                                    placeholder={type.defaultArchiveBase}
                                    spellCheck={false}
                                    autoCapitalize="none"
                                    className="font-mono text-sm"
                                />
                            </Field>
                        )}
                        {type.previous.length > 0 && (
                            <p className="text-xs text-muted-foreground md:col-span-2">
                                {t(
                                    'Old links keep working. They are sent with a 301 redirect from: {patterns}',
                                    { patterns: formatList(type.previous) },
                                )}
                            </p>
                        )}
                    </SectionCard>
                );
            })}

            <SectionCard
                icon="categories"
                title={t('Taxonomy bases')}
                description={t(
                    'The first part of the address of category and tag pages.',
                )}
                contentClassName="grid gap-4 md:grid-cols-2"
            >
                {taxonomies.map((taxonomy) => {
                    const base = data.taxonomy_bases[taxonomy.name] ?? '';

                    return (
                        <Field
                            key={taxonomy.name}
                            label={taxonomy.label}
                            id={`taxonomy-${taxonomy.name}`}
                            error={
                                fieldErrors[`taxonomy_bases.${taxonomy.name}`]
                            }
                            hint={`${origin}${withTrailingSlash(`/${base}/${taxonomy.sample}`, data.trailing_slash)}`}
                        >
                            <Input
                                id={`taxonomy-${taxonomy.name}`}
                                value={base}
                                onChange={(event) =>
                                    setData('taxonomy_bases', {
                                        ...data.taxonomy_bases,
                                        [taxonomy.name]: event.target.value,
                                    })
                                }
                                placeholder={taxonomy.defaultBase}
                                spellCheck={false}
                                autoCapitalize="none"
                                required
                                className="font-mono text-sm"
                            />
                        </Field>
                    );
                })}
            </SectionCard>

            <SectionCard
                icon="settings"
                title={t('Options')}
                description={t('Small rules for every address on the site.')}
                contentClassName="grid gap-3"
            >
                <ToggleRow
                    checked={data.trailing_slash}
                    onChange={(checked) => setData('trailing_slash', checked)}
                    label={t('End addresses with a slash')}
                    hint={t(
                        'Links and canonical tags use /blog/my-post/ instead of /blog/my-post.',
                    )}
                />
                {data.trailing_slash && trailingSlashNotice && (
                    <NoticeBanner
                        tone="warning"
                        icon="warning"
                        title={t('Your web server removes trailing slashes')}
                        description={t(
                            'public/.htaccess sends /blog/ to /blog. Remove the Redirect Trailing Slashes rule, or links will point to a redirect.',
                        )}
                    />
                )}
                <ToggleRow
                    checked={data.lowercase}
                    onChange={(checked) => setData('lowercase', checked)}
                    label={t('Send mixed case addresses to lowercase')}
                    hint={t(
                        '/Blog/My-Post gets a 301 redirect to /blog/my-post.',
                    )}
                />
            </SectionCard>

            <SaveBar
                dirty={form.isDirty}
                processing={processing}
                label={t('Save URL structure')}
            />
        </form>
    );
}

// Buttons that add a token at the end of the pattern.
function TokenList({
    tokens,
    onPick,
}: {
    tokens: string[];
    onPick: (token: string) => void;
}) {
    return (
        <span className="flex flex-wrap items-center gap-1.5">
            <span>{t('Add:')}</span>
            {tokens.map((token) => (
                <button
                    key={token}
                    type="button"
                    onClick={() => onPick(token)}
                    className="min-h-7 rounded border bg-muted/50 px-1.5 font-mono text-xs text-foreground hover:bg-muted"
                >
                    {`{${token}}`}
                </button>
            ))}
        </span>
    );
}

// The example address. It is a polite live region, so screen readers hear changes.
function Preview({
    label,
    url,
    className,
}: {
    label: string;
    url: string;
    className?: string;
}) {
    return (
        <div className={className}>
            <span className="text-xs font-medium text-muted-foreground">
                {label}
            </span>
            <output
                aria-live="polite"
                className="mt-1 block rounded-md border bg-muted/40 px-3 py-2 font-mono text-sm break-all text-foreground"
            >
                {url}
            </output>
        </div>
    );
}
