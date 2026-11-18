import { ChevronDown, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { VersoIcon } from '@/components/verso/icons';
import { MediaPicker } from '@/components/verso/media/media-picker';
import {
    AnalysisList,
    countSummary,
} from '@/components/verso/seo/analysis-list';
import { RATING_LABELS, ScoreRing } from '@/components/verso/seo/score-ring';
import { formatTitle, hostOf } from '@/components/verso/seo/seo-text';
import type { SiteInfo } from '@/components/verso/seo/seo-text';
import { SnippetPreview } from '@/components/verso/seo/snippet-preview';
import { SocialPreview } from '@/components/verso/seo/social-preview';
import { SectionCard } from '@/components/verso/ui';
import { analyze, DEFAULT_ANALYSIS_CONFIG } from '@/lib/seo-analysis';
import type { AnalysisConfig, AnalysisResult } from '@/lib/seo-analysis';
import { formatList, t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { Block, EntrySeoFields } from '@/types';

// What the server sends to the SEO card (EditorAnalysis::props()).
export type SeoAnalysisProps = {
    config: Partial<AnalysisConfig>;
    debounceMs: number;
    site: SiteInfo;
    keyphraseMaxLength: number;
};

// Used when the server sends nothing, like in older plugin screens.
const DEFAULT_DEBOUNCE_MS = 400;

const SHARE_NETWORKS = ['Facebook', 'X', 'LinkedIn'];

// Reads entry.meta.seo safely, with empty values for missing fields.
export function seoFromMeta(meta: Record<string, unknown> | undefined) {
    const raw = (meta?.seo ?? {}) as Partial<EntrySeoFields>;
    const text = (value: unknown) => (typeof value === 'string' ? value : null);

    return {
        title: text(raw.title),
        description: text(raw.description),
        canonical: text(raw.canonical),
        noindex: raw.noindex === true,
        og_image: text(raw.og_image),
        sitemap_exclude: raw.sitemap_exclude === true,
        focus_keyphrase: text(raw.focus_keyphrase),
        nofollow: raw.nofollow === true,
        og_title: text(raw.og_title),
        og_description: text(raw.og_description),
        score:
            typeof raw.score === 'number' && Number.isFinite(raw.score)
                ? raw.score
                : null,
    } satisfies EntrySeoFields;
}

export type SeoPanelProps = {
    value: EntrySeoFields;
    onChange: (value: EntrySeoFields) => void;
    fallbackTitle: string;
    fallbackDescription?: string | null;
    url?: string | null;
    blocks?: Block[];
    slug?: string;
    featuredImage?: string | null;
    analysis?: SeoAnalysisProps | null;
};

// A site description built from the entry address, for screens without server props.
function fallbackSite(url: string | null | undefined): SiteInfo {
    const host = url ? hostOf(url) : '';

    return {
        name: host,
        tagline: '',
        url: url ?? '',
        host,
        locale: document.documentElement.lang || 'en',
        titleFormat: '{title}',
        separator: '|',
        defaultDescription: null,
        defaultImage: null,
        twitterSite: '',
    };
}

// Sidebar card: focus keyphrase, live checks, Google and share previews, and every SEO field of the entry.
export function SeoPanel({
    value,
    onChange,
    fallbackTitle,
    fallbackDescription,
    url,
    blocks,
    slug = '',
    featuredImage = null,
    analysis,
}: SeoPanelProps) {
    const [pickerOpen, setPickerOpen] = useState(false);
    const set = <K extends keyof EntrySeoFields>(
        key: K,
        next: EntrySeoFields[K],
    ) => onChange({ ...value, [key]: next });
    const text = (next: string) => (next.trim() === '' ? null : next);

    const site = analysis?.site ?? fallbackSite(url);
    const config = useMemo(
        () => ({ ...DEFAULT_ANALYSIS_CONFIG, ...analysis?.config }),
        [analysis?.config],
    );
    const pageTitle =
        value.title ??
        (analysis ? formatTitle(fallbackTitle, site) : fallbackTitle);
    const pageDescription =
        value.description ??
        fallbackDescription ??
        site.defaultDescription ??
        '';
    const pageUrl = value.canonical ?? url ?? (site.url || 'https://…');
    const shareImage = value.og_image ?? featuredImage ?? site.defaultImage;

    const result = useDebouncedAnalysis({
        enabled: blocks !== undefined,
        delay: analysis?.debounceMs ?? DEFAULT_DEBOUNCE_MS,
        config,
        input: {
            title: fallbackTitle,
            seoTitle: pageTitle,
            description: value.description ?? fallbackDescription ?? '',
            slug,
            keyphrase: value.focus_keyphrase ?? '',
            blocks: blocks ?? [],
            locale: site.locale,
            siteHost: site.host,
            hasFeaturedImage: Boolean(featuredImage || value.og_image),
        },
    });

    useScoreSync(result, value, onChange);

    const seoChecks =
        result?.checks.filter((check) => check.group === 'seo') ?? [];
    const readabilityChecks =
        result?.checks.filter((check) => check.group === 'readability') ?? [];

    return (
        <SectionCard
            icon="seo"
            title={t('SEO')}
            contentClassName="grid grid-cols-[minmax(0,1fr)] gap-4"
            actions={
                result && result.rating !== 'none' ? (
                    <span className="flex items-center gap-2 text-xs text-muted-foreground">
                        {t(RATING_LABELS[result.rating])}
                        <ScoreRing
                            score={result.score}
                            rating={result.rating}
                            label={t('SEO score of this entry')}
                        />
                    </span>
                ) : null
            }
        >
            <div className="grid min-w-0 gap-2">
                <Label htmlFor="seo-keyphrase">{t('Focus keyphrase')}</Label>
                <Input
                    id="seo-keyphrase"
                    value={value.focus_keyphrase ?? ''}
                    maxLength={analysis?.keyphraseMaxLength}
                    placeholder={t('What would people search for?')}
                    aria-describedby="seo-keyphrase-help"
                    onChange={(event) =>
                        set('focus_keyphrase', text(event.target.value))
                    }
                />
                <p
                    id="seo-keyphrase-help"
                    className="text-xs text-muted-foreground"
                >
                    {t(
                        'The words you want this page to be found for, like “sourdough bread at home”.',
                    )}
                </p>
            </div>

            <div aria-label={t('Search result preview')} role="group">
                <SnippetPreview
                    title={pageTitle}
                    description={pageDescription}
                    url={pageUrl}
                    siteName={site.name || hostOf(pageUrl)}
                />
            </div>

            <div className="grid min-w-0 gap-2">
                <Label htmlFor="seo-title">{t('SEO title')}</Label>
                <Input
                    id="seo-title"
                    value={value.title ?? ''}
                    placeholder={fallbackTitle}
                    onChange={(event) => set('title', text(event.target.value))}
                />
                <Counter
                    length={value.title?.length ?? 0}
                    hint={config.titleMax}
                />
            </div>

            <div className="grid min-w-0 gap-2">
                <Label htmlFor="seo-description">{t('Meta description')}</Label>
                <Textarea
                    id="seo-description"
                    value={value.description ?? ''}
                    placeholder={fallbackDescription ?? ''}
                    rows={3}
                    onChange={(event) =>
                        set('description', text(event.target.value))
                    }
                />
                <Counter
                    length={value.description?.length ?? 0}
                    hint={config.descriptionMax}
                />
            </div>

            {result && (
                <>
                    <Disclosure
                        title={t('SEO checks')}
                        summary={countSummary(seoChecks)}
                        defaultOpen
                    >
                        <AnalysisList
                            checks={seoChecks}
                            empty={t('Write some content to get tips.')}
                        />
                    </Disclosure>
                    <Disclosure
                        title={t('Readability')}
                        summary={
                            readabilityChecks.length > 0
                                ? countSummary(readabilityChecks)
                                : t('Write more to check')
                        }
                    >
                        <AnalysisList
                            checks={readabilityChecks}
                            empty={t(
                                'Readability tips show up after a few paragraphs.',
                            )}
                        />
                    </Disclosure>
                </>
            )}

            <Disclosure
                title={t('Social sharing')}
                summary={formatList(SHARE_NETWORKS, 'unit')}
            >
                <SocialPreview
                    title={value.og_title ?? pageTitle}
                    description={value.og_description ?? pageDescription}
                    image={shareImage}
                    url={pageUrl}
                />
                <div className="grid min-w-0 gap-2">
                    <Label htmlFor="seo-og-title">{t('Share title')}</Label>
                    <Input
                        id="seo-og-title"
                        value={value.og_title ?? ''}
                        placeholder={pageTitle}
                        onChange={(event) =>
                            set('og_title', text(event.target.value))
                        }
                    />
                </div>
                <div className="grid min-w-0 gap-2">
                    <Label htmlFor="seo-og-description">
                        {t('Share description')}
                    </Label>
                    <Textarea
                        id="seo-og-description"
                        value={value.og_description ?? ''}
                        placeholder={pageDescription}
                        rows={2}
                        onChange={(event) =>
                            set('og_description', text(event.target.value))
                        }
                    />
                </div>
                <div className="grid min-w-0 gap-2">
                    <Label htmlFor="seo-og-image">{t('Share image')}</Label>
                    {value.og_image && (
                        <div className="relative overflow-hidden rounded-md border">
                            <img
                                src={value.og_image}
                                alt=""
                                className="aspect-[1.91/1] w-full object-cover"
                            />
                            <Button
                                type="button"
                                variant="secondary"
                                size="icon"
                                className="absolute end-2 top-2 size-8"
                                onClick={() => set('og_image', null)}
                                aria-label={t('Remove share image')}
                            >
                                <X />
                            </Button>
                        </div>
                    )}
                    <div className="flex min-w-0 gap-2">
                        <Input
                            id="seo-og-image"
                            value={value.og_image ?? ''}
                            placeholder={t('Image URL')}
                            onChange={(event) =>
                                set('og_image', text(event.target.value))
                            }
                        />
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            className="size-10 shrink-0 md:size-9"
                            onClick={() => setPickerOpen(true)}
                            aria-label={t('Pick from the media library')}
                        >
                            <VersoIcon name="media" size={16} />
                        </Button>
                    </div>
                </div>
            </Disclosure>

            <Disclosure
                title={t('Advanced')}
                summary={t('Canonical, robots, sitemap')}
            >
                <div className="grid min-w-0 gap-2">
                    <Label htmlFor="seo-canonical">{t('Canonical URL')}</Label>
                    <Input
                        id="seo-canonical"
                        type="url"
                        value={value.canonical ?? ''}
                        placeholder={url ?? 'https://'}
                        autoComplete="off"
                        spellCheck={false}
                        onChange={(event) =>
                            set('canonical', text(event.target.value))
                        }
                    />
                    <p className="text-xs text-muted-foreground">
                        {t('Only when the same text lives at another address.')}
                    </p>
                </div>

                <CheckboxRow
                    checked={value.noindex}
                    onChange={(checked) => set('noindex', checked)}
                    label={t('Hide from search engines')}
                    help={t('Adds noindex and leaves it out of the sitemap.')}
                />
                <CheckboxRow
                    checked={value.nofollow}
                    onChange={(checked) => set('nofollow', checked)}
                    label={t('Do not follow links on this page')}
                    help={t(
                        'Adds nofollow, so search engines give no weight to its links.',
                    )}
                />
                <CheckboxRow
                    checked={value.sitemap_exclude || value.noindex}
                    disabled={value.noindex}
                    onChange={(checked) => set('sitemap_exclude', checked)}
                    label={t('Leave out of the sitemap')}
                    help={t('Search engines can still find it through links.')}
                />
            </Disclosure>

            <MediaPicker
                open={pickerOpen}
                onOpenChange={setPickerOpen}
                accept="image"
                onSelect={(media) => {
                    set('og_image', media.url);
                    setPickerOpen(false);
                }}
            />
        </SectionCard>
    );
}

// Runs the analysis a short time after the last change, so typing stays fast.
function useDebouncedAnalysis({
    enabled,
    delay,
    config,
    input,
}: {
    enabled: boolean;
    delay: number;
    config: AnalysisConfig;
    input: Parameters<typeof analyze>[0];
}): AnalysisResult | null {
    const key = JSON.stringify(input);
    const [result, setResult] = useState<AnalysisResult | null>(() =>
        enabled ? analyze(input, config) : null,
    );
    const inputRef = useRef(input);
    inputRef.current = input;
    // The input and config of the shown result, so the same input never runs twice.
    const done = useRef<{ key: string; config: AnalysisConfig } | null>(
        enabled ? { key, config } : null,
    );

    useEffect(() => {
        if (
            !enabled ||
            (done.current?.key === key && done.current.config === config)
        ) {
            return;
        }

        const timer = window.setTimeout(
            () => {
                done.current = { key, config };
                setResult(analyze(inputRef.current, config));
            },
            Math.max(0, delay),
        );

        return () => window.clearTimeout(timer);
    }, [key, enabled, delay, config]);

    return result;
}

// Saves the score with the entry for the traffic light in lists. The first result
// is not written, so opening an entry never marks it as changed.
function useScoreSync(
    result: AnalysisResult | null,
    value: EntrySeoFields,
    onChange: (value: EntrySeoFields) => void,
) {
    const first = useRef<AnalysisResult | null>(result);
    const latest = useRef({ value, onChange });
    latest.current = { value, onChange };

    useEffect(() => {
        if (!result || result === first.current || result.rating === 'none') {
            return;
        }

        const score = Math.round(result.score);

        if (latest.current.value.score !== score) {
            latest.current.onChange({ ...latest.current.value, score });
        }
    }, [result]);
}

// A native <details> section with a short summary next to its title.
function Disclosure({
    title,
    summary,
    defaultOpen = false,
    children,
}: {
    title: string;
    summary?: string;
    defaultOpen?: boolean;
    children: ReactNode;
}) {
    return (
        <details
            className="group min-w-0 rounded-md border"
            open={defaultOpen || undefined}
        >
            <summary className="flex min-h-10 cursor-pointer list-none items-center gap-2 px-3 py-2 text-sm font-medium md:min-h-9 [&::-webkit-details-marker]:hidden">
                <span className="shrink-0">{title}</span>
                {summary && (
                    <span className="min-w-0 flex-1 truncate text-end text-xs font-normal text-muted-foreground">
                        {summary}
                    </span>
                )}
                <ChevronDown
                    className="size-4 shrink-0 text-muted-foreground transition-transform group-open:rotate-180 motion-reduce:transition-none"
                    aria-hidden="true"
                />
            </summary>
            <div className="grid min-w-0 gap-4 border-t px-3 py-3">
                {children}
            </div>
        </details>
    );
}

function CheckboxRow({
    checked,
    onChange,
    label,
    help,
    disabled = false,
}: {
    checked: boolean;
    onChange: (checked: boolean) => void;
    label: string;
    help: string;
    disabled?: boolean;
}) {
    return (
        <label className="flex items-start gap-3 text-sm">
            <Checkbox
                checked={checked}
                disabled={disabled}
                onCheckedChange={(next) => onChange(next === true)}
                className="mt-0.5"
            />
            <span className="min-w-0">
                {label}
                <span className="block text-xs text-muted-foreground">
                    {help}
                </span>
            </span>
        </label>
    );
}

// Shows "42 / 60" and turns amber when the text is longer than the hint.
function Counter({ length, hint }: { length: number; hint: number }) {
    return (
        <p
            className={cn(
                'text-end text-xs tabular-nums',
                length > hint ? 'text-warning-text' : 'text-muted-foreground',
            )}
        >
            {t('{length, number} / {hint, number}', { length, hint })}
        </p>
    );
}

export default SeoPanel;
