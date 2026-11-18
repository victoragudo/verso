import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Pagination } from '@/components/verso/content/pagination';
import {
    EmptyState,
    FilterTabs,
    NoticeBanner,
    PageHeader,
} from '@/components/verso/ui';
import { msg, t } from '@/lib/i18n';
import { xsrfToken } from '@/lib/json-request';
import { index } from '@/routes/admin/seo';
import { index as altTextIndex, update } from '@/routes/admin/seo/alt-text';
import type { MediaLibraryItem, Paginated } from '@/types';

type Suggester = {
    label: string;
    endpoint: string;
    ready: boolean;
    settingsUrl: string | null;
};

type Props = {
    images: Paginated<MediaLibraryItem>;
    filters: { show: 'missing' | 'all'; search: string };
    missing: number;
    maxLength: number;
    suggester: Suggester | null;
};

type Suggestion =
    | { state: 'loading' }
    | { state: 'ready'; alt: string }
    | { state: 'error'; message: string };

// Asks the suggester for alt text. The answer is only a preview until the person accepts it.
export async function fetchSuggestion(
    endpoint: string,
    mediaId: number,
): Promise<string> {
    const response = await fetch(endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: JSON.stringify({ media_id: mediaId }),
    });
    const data = (await response.json().catch(() => ({}))) as {
        result?: { alt?: unknown };
        message?: unknown;
    };

    if (!response.ok || typeof data.result?.alt !== 'string') {
        throw new Error(
            typeof data.message === 'string'
                ? data.message
                : t('No suggestion this time. Try again later.'),
        );
    }

    return data.result.alt;
}

// Bulk "Add alt text": every image of the page gets a text field, saved together.
export default function AltTextPage({
    images,
    filters,
    missing,
    maxLength,
    suggester,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [values, setValues] = useState<Record<number, string>>(() =>
        Object.fromEntries(
            images.data.map((image) => [image.id, image.alt ?? '']),
        ),
    );
    const [suggestions, setSuggestions] = useState<Record<number, Suggestion>>(
        {},
    );
    const [saving, setSaving] = useState(false);

    const changed = images.data.filter(
        (image) => (values[image.id] ?? '') !== (image.alt ?? ''),
    );
    const reload = (query: Partial<Props['filters']>) =>
        router.get(
            altTextIndex.url({ query: { ...filters, ...query } }),
            {},
            { preserveState: false },
        );
    const setSuggestion = (id: number, suggestion: Suggestion | null) =>
        setSuggestions((current) => {
            const next = { ...current };

            if (suggestion === null) {
                delete next[id];
            } else {
                next[id] = suggestion;
            }

            return next;
        });

    const suggest = async (image: MediaLibraryItem) => {
        if (!suggester) {
            return;
        }

        setSuggestion(image.id, { state: 'loading' });

        try {
            setSuggestion(image.id, {
                state: 'ready',
                alt: await fetchSuggestion(suggester.endpoint, image.id),
            });
        } catch (error) {
            setSuggestion(image.id, {
                state: 'error',
                message: (error as Error).message,
            });
        }
    };

    const save = () => {
        setSaving(true);
        router.put(
            update.url(),
            {
                items: changed.map((image) => ({
                    id: image.id,
                    alt: values[image.id] ?? '',
                })),
            },
            { preserveScroll: true, onFinish: () => setSaving(false) },
        );
    };

    return (
        <>
            <Head title={t('Add alt text')} />
            <PageHeader
                icon="media"
                title={t('Add alt text')}
                description={t(
                    'Describe each image in a short sentence for search engines and people who cannot see it. {count, plural, one {# image needs} other {# images need}} alt text.',
                    { count: missing },
                )}
                actions={
                    <Button
                        asChild
                        variant="ghost"
                        className="min-h-10 md:min-h-9"
                    >
                        <Link href={index.url()}>
                            {t('Back to the overview')}
                        </Link>
                    </Button>
                }
            />

            {suggester && !suggester.ready && (
                <NoticeBanner
                    className="mb-4"
                    tone="info"
                    title={t('{name} can suggest alt text', {
                        name: suggester.label,
                    })}
                    description={t(
                        'Add an API key in its settings to get suggestions here.',
                    )}
                    href={suggester.settingsUrl}
                    action={suggester.settingsUrl ? t('Open settings') : null}
                />
            )}

            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <FilterTabs
                    tabs={[
                        {
                            key: 'missing',
                            label: t('Without alt text'),
                            href: altTextIndex.url({
                                query: {
                                    show: 'missing',
                                    search: filters.search,
                                },
                            }),
                        },
                        {
                            key: 'all',
                            label: t('All images'),
                            href: altTextIndex.url({
                                query: { show: 'all', search: filters.search },
                            }),
                        },
                    ]}
                    active={filters.show}
                    label={t('Images to show')}
                />
                <form
                    role="search"
                    className="relative w-full sm:w-72"
                    onSubmit={(event) => {
                        event.preventDefault();
                        reload({ search });
                    }}
                >
                    <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder={t('Search images')}
                        aria-label={t('Search images')}
                        className="ps-9"
                    />
                </form>
            </div>

            {images.data.length === 0 ? (
                <EmptyState
                    icon="media"
                    title={
                        filters.show === 'missing'
                            ? t('Every image has alt text. Well done.')
                            : t('No images found.')
                    }
                />
            ) : (
                <ul className="auto-grid gap-4 [--grid-min:17rem]" role="list">
                    {images.data.map((image) => {
                        const suggestion = suggestions[image.id];
                        const fieldId = `alt-${image.id}`;

                        return (
                            <li
                                key={image.id}
                                className="flex min-w-0 flex-col overflow-hidden rounded-lg border bg-card shadow-xs"
                            >
                                <img
                                    src={image.thumbnail_url ?? image.url}
                                    alt=""
                                    loading="lazy"
                                    className="aspect-[4/3] w-full bg-muted object-cover"
                                />
                                <div className="grid gap-2 p-3">
                                    <Label
                                        htmlFor={fieldId}
                                        className="truncate text-xs text-muted-foreground"
                                        title={image.filename}
                                    >
                                        {t('Alt text for {filename}', {
                                            filename: image.filename,
                                        })}
                                    </Label>
                                    <Textarea
                                        id={fieldId}
                                        rows={2}
                                        maxLength={maxLength}
                                        value={values[image.id] ?? ''}
                                        disabled={!image.can.update}
                                        placeholder={t(
                                            'Two bakers shaping rye loaves at dawn',
                                        )}
                                        onChange={(event) =>
                                            setValues((current) => ({
                                                ...current,
                                                [image.id]: event.target.value,
                                            }))
                                        }
                                    />
                                    {suggester?.ready && image.can.update && (
                                        <div className="grid gap-2">
                                            {suggestion?.state === 'ready' ? (
                                                <div
                                                    className="grid gap-2 rounded-md border bg-muted/40 p-2 text-sm"
                                                    aria-live="polite"
                                                >
                                                    <p>
                                                        <span className="text-xs text-muted-foreground">
                                                            {t(
                                                                'Suggestion:',
                                                            )}{' '}
                                                        </span>
                                                        {suggestion.alt}
                                                    </p>
                                                    <div className="flex gap-2">
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            className="min-h-10 md:min-h-8"
                                                            onClick={() => {
                                                                setValues(
                                                                    (
                                                                        current,
                                                                    ) => ({
                                                                        ...current,
                                                                        [image.id]:
                                                                            suggestion.alt.slice(
                                                                                0,
                                                                                maxLength,
                                                                            ),
                                                                    }),
                                                                );
                                                                setSuggestion(
                                                                    image.id,
                                                                    null,
                                                                );
                                                            }}
                                                        >
                                                            {t('Use it')}
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="ghost"
                                                            className="min-h-10 md:min-h-8"
                                                            onClick={() =>
                                                                setSuggestion(
                                                                    image.id,
                                                                    null,
                                                                )
                                                            }
                                                        >
                                                            {t('Discard')}
                                                        </Button>
                                                    </div>
                                                </div>
                                            ) : (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    className="min-h-10 md:min-h-8"
                                                    disabled={
                                                        suggestion?.state ===
                                                        'loading'
                                                    }
                                                    onClick={() =>
                                                        suggest(image)
                                                    }
                                                >
                                                    {suggestion?.state ===
                                                    'loading'
                                                        ? t('Thinking…')
                                                        : t(
                                                              'Suggest with {name}',
                                                              {
                                                                  name: suggester.label,
                                                              },
                                                          )}
                                                </Button>
                                            )}
                                            {suggestion?.state === 'error' && (
                                                <p
                                                    className="text-xs text-danger-text"
                                                    role="alert"
                                                >
                                                    {suggestion.message}
                                                </p>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}

            <Pagination page={images} />

            {images.data.length > 0 && (
                <div className="sticky bottom-0 z-10 mt-4 flex flex-wrap items-center justify-end gap-3 border-t bg-background/95 py-3 backdrop-blur">
                    <span
                        className="text-sm text-muted-foreground"
                        aria-live="polite"
                    >
                        {changed.length === 0
                            ? t('No changes yet.')
                            : t(
                                  '{count, plural, one {# image} other {# images}} changed.',
                                  { count: changed.length },
                              )}
                    </span>
                    <Button
                        type="button"
                        className="min-h-10 md:min-h-9"
                        disabled={changed.length === 0 || saving}
                        onClick={save}
                    >
                        {t('Save alt text')}
                    </Button>
                </div>
            )}
        </>
    );
}

AltTextPage.layout = {
    breadcrumbs: [
        { title: msg('SEO overview'), href: index() },
        { title: msg('Add alt text'), href: altTextIndex() },
    ],
};
