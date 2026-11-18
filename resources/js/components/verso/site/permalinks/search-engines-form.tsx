import { useForm } from '@inertiajs/react';
import { useRef } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Field, ToggleRow } from '@/components/verso/site/settings-fields';
import { SearchableSelect, SectionCard } from '@/components/verso/ui';
import { t, Trans } from '@/lib/i18n';
import { search } from '@/routes/admin/site/permalinks';
import type { SearchEngineSettings } from '@/types';
import { MediaField } from './media-field';
import { focusFirstError, SaveBar } from './save-bar';

type Props = {
    values: SearchEngineSettings;
    robotsDefault: string;
    robotsUrl: string;
    siteUrl: string;
    site: { name: string; tagline: string };
};

// The form as sent: images as ids and profile links as a list.
type SearchForm = Omit<SearchEngineSettings, 'schema_same_as'> & {
    schema_same_as: string;
};

const TITLE_TOKENS = ['{title}', '{sep}', '{site}', '{tagline}'];

// The page title the format gives, like "Winter Menu | Harbor Lane".
export function previewTitle(
    format: string,
    separator: string,
    site: { name: string; tagline: string },
    title = 'Winter Menu',
): string {
    const sep = ` ${separator.trim()} `;
    const text = format
        .replaceAll('{title}', title)
        .replaceAll('{sep}', sep)
        .replaceAll('{site}', site.name)
        .replaceAll('{tagline}', site.tagline)
        .trim();
    const symbol = separator.trim();

    if (symbol === '') {
        return text;
    }

    let trimmed = text;
    if (trimmed.startsWith(symbol)) trimmed = trimmed.slice(symbol.length);
    if (trimmed.endsWith(symbol)) trimmed = trimmed.slice(0, -symbol.length);

    return trimmed.trim() || title;
}

// A random IndexNow key: 32 letters and digits.
export function newIndexNowKey(): string {
    return crypto.randomUUID().replaceAll('-', '');
}

// Visibility, robots.txt, titles, defaults, schema.org, verification codes and IndexNow.
export function SearchEnginesForm({
    values,
    robotsDefault,
    robotsUrl,
    siteUrl,
    site,
}: Props) {
    const form = useForm<SearchForm>({
        ...values,
        schema_same_as: values.schema_same_as.join('\n'),
    });
    const { data, setData, errors, processing } = form;
    const formRef = useRef<HTMLFormElement>(null);
    const fieldErrors = errors as Record<string, string | undefined>;
    const sameAsError = Object.entries(fieldErrors).find(([key]) =>
        key.startsWith('schema_same_as'),
    )?.[1];

    form.transform((current) => ({
        ...current,
        default_image: current.default_image?.id ?? null,
        schema_logo: current.schema_logo?.id ?? null,
        schema_same_as: current.schema_same_as
            .split('\n')
            .map((line) => line.trim())
            .filter((line) => line !== ''),
    }));

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(search.url(), {
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
                icon="shield"
                title={t('Visibility')}
                contentClassName="grid gap-4"
            >
                <ToggleRow
                    checked={data.discourage}
                    onChange={(checked) => setData('discourage', checked)}
                    label={t('Discourage search engines')}
                    hint={t(
                        'Adds noindex to every page and blocks all robots in robots.txt. Use it for a site that is not ready.',
                    )}
                />
                <Field
                    label="robots.txt"
                    id="robots_txt"
                    error={fieldErrors.robots_txt}
                    hint={
                        <Trans
                            text={t(
                                'The sitemap line is added for you. Live at <link>{url}</link>',
                                { url: robotsUrl },
                            )}
                            components={{
                                link: (text) => (
                                    <a
                                        href={robotsUrl}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="break-all text-brand hover:underline"
                                    >
                                        {text}
                                    </a>
                                ),
                            }}
                        />
                    }
                >
                    <Textarea
                        id="robots_txt"
                        value={data.robots_txt}
                        onChange={(event) =>
                            setData('robots_txt', event.target.value)
                        }
                        rows={7}
                        spellCheck={false}
                        disabled={data.discourage}
                        className="font-mono text-xs"
                    />
                    <div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="min-h-10 md:min-h-8"
                            onClick={() => setData('robots_txt', robotsDefault)}
                        >
                            {t('Use safe default')}
                        </Button>
                    </div>
                </Field>
            </SectionCard>

            <SectionCard
                icon="seo"
                title={t('Titles and defaults')}
                description={t(
                    'Used when an entry has no SEO title, description or share image of its own.',
                )}
                contentClassName="grid gap-4 md:grid-cols-2"
            >
                <Field
                    label={t('Title format')}
                    id="title_format"
                    error={fieldErrors.title_format}
                    hint={t('Tokens: {tokens}', {
                        tokens: TITLE_TOKENS.join(', '),
                    })}
                >
                    <Input
                        id="title_format"
                        value={data.title_format}
                        onChange={(event) =>
                            setData('title_format', event.target.value)
                        }
                        spellCheck={false}
                        className="font-mono text-sm"
                    />
                </Field>
                <Field
                    label={t('Separator')}
                    id="title_separator"
                    error={fieldErrors.title_separator}
                >
                    <Input
                        id="title_separator"
                        value={data.title_separator}
                        onChange={(event) =>
                            setData('title_separator', event.target.value)
                        }
                        className="font-mono text-sm"
                    />
                </Field>
                <div className="md:col-span-2">
                    <span className="text-xs font-medium text-muted-foreground">
                        {t('Example title')}
                    </span>
                    <output
                        aria-live="polite"
                        className="mt-1 block rounded-md border bg-muted/40 px-3 py-2 text-sm break-words"
                    >
                        {previewTitle(
                            data.title_format,
                            data.title_separator,
                            site,
                            t('Winter Menu'),
                        )}
                    </output>
                </div>
                <Field
                    label={t('Default meta description')}
                    id="default_description"
                    error={fieldErrors.default_description}
                    className="md:col-span-2"
                >
                    <Textarea
                        id="default_description"
                        value={data.default_description}
                        onChange={(event) =>
                            setData('default_description', event.target.value)
                        }
                        rows={3}
                        placeholder={site.tagline}
                    />
                </Field>
                <Field
                    label={t('Default share image')}
                    id="default_image"
                    error={fieldErrors.default_image}
                    hint={t(
                        'Shown when a page is shared on social networks. 1200 × 630 pixels works best.',
                    )}
                    className="md:col-span-2"
                >
                    <MediaField
                        id="default_image"
                        value={data.default_image}
                        onChange={(media) => setData('default_image', media)}
                        emptyText={t('No image yet')}
                    />
                </Field>
            </SectionCard>

            <SectionCard
                icon="site"
                title={t('Who runs this site')}
                description={t('Shown to search engines as schema.org data.')}
                contentClassName="grid gap-4 md:grid-cols-2"
            >
                <Field
                    label={t('Type')}
                    id="schema_type"
                    error={fieldErrors.schema_type}
                >
                    <SearchableSelect
                        id="schema_type"
                        value={data.schema_type}
                        onValueChange={(value) =>
                            setData(
                                'schema_type',
                                value as SearchForm['schema_type'],
                            )
                        }
                        options={[
                            { value: 'organization', label: t('Organization') },
                            { value: 'person', label: t('Person') },
                        ]}
                        className="w-full"
                    />
                </Field>
                <Field
                    label={
                        data.schema_type === 'person'
                            ? t('Full name')
                            : t('Organization name')
                    }
                    id="schema_name"
                    error={fieldErrors.schema_name}
                    hint={t('Empty leaves this data out.')}
                >
                    <Input
                        id="schema_name"
                        value={data.schema_name}
                        onChange={(event) =>
                            setData('schema_name', event.target.value)
                        }
                        placeholder={site.name}
                        autoComplete={
                            data.schema_type === 'person'
                                ? 'name'
                                : 'organization'
                        }
                    />
                </Field>
                <Field
                    label={
                        data.schema_type === 'person' ? t('Photo') : t('Logo')
                    }
                    id="schema_logo"
                    error={fieldErrors.schema_logo}
                    className="md:col-span-2"
                >
                    <MediaField
                        id="schema_logo"
                        value={data.schema_logo}
                        onChange={(media) => setData('schema_logo', media)}
                        emptyText={t('No image yet')}
                    />
                </Field>
                <Field
                    label={t('Profiles')}
                    id="schema_same_as"
                    error={sameAsError}
                    hint={t(
                        'One link per line, like your Instagram or LinkedIn page.',
                    )}
                    className="md:col-span-2"
                >
                    <Textarea
                        id="schema_same_as"
                        value={data.schema_same_as}
                        onChange={(event) =>
                            setData('schema_same_as', event.target.value)
                        }
                        rows={3}
                        spellCheck={false}
                        inputMode="url"
                        className="font-mono text-xs"
                    />
                </Field>
            </SectionCard>

            <SectionCard
                icon="shield"
                title={t('Verification')}
                description={t(
                    'Paste only the code from the content attribute of the meta tag.',
                )}
                contentClassName="grid gap-4 md:grid-cols-2"
            >
                <Field
                    label="Google Search Console"
                    id="verification_google"
                    error={fieldErrors.verification_google}
                >
                    <Input
                        id="verification_google"
                        value={data.verification_google}
                        onChange={(event) =>
                            setData('verification_google', event.target.value)
                        }
                        spellCheck={false}
                        autoComplete="off"
                        className="font-mono text-sm"
                    />
                </Field>
                <Field
                    label="Bing Webmaster Tools"
                    id="verification_bing"
                    error={fieldErrors.verification_bing}
                >
                    <Input
                        id="verification_bing"
                        value={data.verification_bing}
                        onChange={(event) =>
                            setData('verification_bing', event.target.value)
                        }
                        spellCheck={false}
                        autoComplete="off"
                        className="font-mono text-sm"
                    />
                </Field>
            </SectionCard>

            <SectionCard
                icon="updates"
                title="IndexNow"
                description={t(
                    'Tells Bing, Yandex and other engines about new posts right away.',
                )}
                contentClassName="grid gap-4"
            >
                <ToggleRow
                    checked={data.indexnow_enabled}
                    onChange={(checked) => {
                        setData((current) => ({
                            ...current,
                            indexnow_enabled: checked,
                            indexnow_key:
                                checked && current.indexnow_key === ''
                                    ? newIndexNowKey()
                                    : current.indexnow_key,
                        }));
                    }}
                    label={t('Ping IndexNow when an entry is published')}
                    hint={t('Sends only the address of the new page.')}
                />
                {data.indexnow_enabled && (
                    <Field
                        label={t('Key')}
                        id="indexnow_key"
                        error={fieldErrors.indexnow_key}
                        hint={t('Served at {url}', {
                            url: `${siteUrl.replace(/\/$/, '')}/${data.indexnow_key || 'key'}.txt`,
                        })}
                    >
                        <div className="flex flex-wrap gap-2">
                            <Input
                                id="indexnow_key"
                                value={data.indexnow_key}
                                onChange={(event) =>
                                    setData('indexnow_key', event.target.value)
                                }
                                spellCheck={false}
                                autoComplete="off"
                                className="min-w-0 flex-1 font-mono text-sm"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                className="min-h-10 md:min-h-9"
                                onClick={() =>
                                    setData('indexnow_key', newIndexNowKey())
                                }
                            >
                                {t('New key')}
                            </Button>
                        </div>
                    </Field>
                )}
            </SectionCard>

            <SaveBar
                dirty={form.isDirty}
                processing={processing}
                label={t('Save search engine settings')}
            />
        </form>
    );
}
