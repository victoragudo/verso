import type { Editor } from '@tiptap/react';
import { Check, X } from 'lucide-react';
import { useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { SectionCard } from '@/components/verso/ui';
import { languageName, t } from '@/lib/i18n';
import type { Block } from '@/types';
import { AiRequestError, askAssistant } from '../ai-api';
import {
    blocksToText,
    entryImages,
    htmlToText,
    listItems,
} from '../block-helpers';
import type {
    AiAssistantProps,
    AltTextResult,
    ExcerptResult,
    OutlineResult,
    RewriteResult,
    SeoResult,
    TranslateResult,
} from '../types';

// What the panel can change in the editor. Each call happens only when the user accepts.
export type AssistantApply = {
    outline: (blocks: Block[]) => void;
    excerpt: (excerpt: string) => void;
    seo: (seo: SeoResult) => void;
    imageAlt: (mediaId: number, alt: string) => void;
    translation: (result: TranslateResult) => void;
};

type Props = {
    config: AiAssistantProps;
    title: string;
    blocks: Block[];
    editor: Editor | null;
    apply: AssistantApply;
};

type Action =
    | 'outline'
    | 'improve'
    | 'shorten'
    | 'excerpt'
    | 'seo'
    | 'alt'
    | 'translate';

// A suggestion waiting for the user to accept or discard it.
type Preview =
    | { kind: 'outline'; result: OutlineResult }
    | {
          kind: 'rewrite';
          result: RewriteResult;
          original: string;
          editor: Editor;
          from: number;
          to: number;
      }
    | { kind: 'excerpt'; result: ExcerptResult }
    | { kind: 'seo'; result: SeoResult }
    | { kind: 'alt'; result: AltTextResult; mediaId: number; label: string }
    | { kind: 'translate'; result: TranslateResult; locale: string };

const NO_IMAGE = 'none';

// Most preview lines shown for a translation.
const TRANSLATION_PREVIEW_LINES = 8;

// AI writing help for the entry editor. Every result is a preview the user accepts or discards.
export function AssistantPanel({
    config,
    title,
    blocks,
    editor,
    apply,
}: Props) {
    const [busy, setBusy] = useState<Action | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [preview, setPreview] = useState<Preview | null>(null);
    const [locale, setLocale] = useState(config.locales[0]?.code ?? '');
    const [imageId, setImageId] = useState(NO_IMAGE);
    const abort = useRef<AbortController | null>(null);

    const images = entryImages(blocks);
    const selectedImage = images.find(
        (image) => String(image.mediaId) === imageId,
    );

    const run = async <T,>(
        action: Action,
        url: string,
        body: Record<string, unknown>,
        show: (result: T) => Preview,
    ) => {
        abort.current?.abort();
        const controller = new AbortController();
        abort.current = controller;

        setBusy(action);
        setError(null);
        setPreview(null);

        try {
            const result = await askAssistant<T>(url, body, controller.signal);
            setPreview(show(result));
        } catch (caught) {
            if (caught instanceof AiRequestError) {
                setError(caught.message);
            } else if (
                !(
                    caught instanceof DOMException &&
                    caught.name === 'AbortError'
                )
            ) {
                setError(t('Something went wrong.'));
            }
        } finally {
            if (abort.current === controller) {
                setBusy(null);
            }
        }
    };

    const rewrite = (mode: 'improve' | 'shorten') => {
        if (!editor || editor.isDestroyed) {
            setError(
                t(
                    'ai-assistant::Click into a text block and select some text first.',
                ),
            );

            return;
        }

        const { from, to } = editor.state.selection;
        const text = editor.state.doc.textBetween(from, to, '\n').trim();

        if (text === '') {
            setError(t('ai-assistant::Select some text in the editor first.'));

            return;
        }

        void run<RewriteResult>(
            mode,
            config.endpoints.rewrite,
            { text, mode },
            (result) => ({
                kind: 'rewrite',
                result,
                original: text,
                editor,
                from,
                to,
            }),
        );
    };

    const accept = () => {
        if (!preview) {
            return;
        }

        switch (preview.kind) {
            case 'outline':
                apply.outline(preview.result.blocks);
                break;
            case 'rewrite':
                if (!preview.editor.isDestroyed) {
                    preview.editor
                        .chain()
                        .focus()
                        .insertContentAt(
                            { from: preview.from, to: preview.to },
                            { type: 'text', text: preview.result.text },
                        )
                        .run();
                }
                break;
            case 'excerpt':
                apply.excerpt(preview.result.excerpt);
                break;
            case 'seo':
                apply.seo(preview.result);
                break;
            case 'alt':
                apply.imageAlt(preview.mediaId, preview.result.alt);
                break;
            case 'translate':
                apply.translation(preview.result);
                break;
        }

        setPreview(null);
    };

    const hasText = title.trim() !== '' || blocks.length > 0;

    if (!config.ready) {
        return (
            <SectionCard
                title={t('ai-assistant::AI assistant')}
                icon="sparkles"
            >
                <p className="text-sm text-muted-foreground">
                    {t(
                        'ai-assistant::The assistant needs an API key before it can help.',
                    )}
                </p>
                {config.settingsUrl && (
                    <Button
                        variant="outline"
                        size="sm"
                        className="mt-3"
                        asChild
                    >
                        <a href={config.settingsUrl}>
                            {t('ai-assistant::Open AI settings')}
                        </a>
                    </Button>
                )}
            </SectionCard>
        );
    }

    return (
        <SectionCard
            title={t('ai-assistant::AI assistant')}
            icon="sparkles"
            description={t(
                'ai-assistant::Suggestions are previews. Nothing changes until you accept.',
            )}
            contentClassName="grid gap-4"
        >
            <ActionGroup label={t('ai-assistant::Write')}>
                <ActionButton
                    busy={busy === 'outline'}
                    disabled={busy !== null || title.trim() === ''}
                    onClick={() =>
                        run<OutlineResult>(
                            'outline',
                            config.endpoints.outline,
                            { title },
                            (result) => ({ kind: 'outline', result }),
                        )
                    }
                >
                    {t('ai-assistant::Draft outline')}
                </ActionButton>
                <ActionButton
                    busy={busy === 'excerpt'}
                    disabled={busy !== null || !hasText}
                    onClick={() =>
                        run<ExcerptResult>(
                            'excerpt',
                            config.endpoints.excerpt,
                            { title, blocks },
                            (result) => ({ kind: 'excerpt', result }),
                        )
                    }
                >
                    {t('ai-assistant::Write excerpt')}
                </ActionButton>
                <ActionButton
                    busy={busy === 'seo'}
                    disabled={busy !== null || !hasText}
                    onClick={() =>
                        run<SeoResult>(
                            'seo',
                            config.endpoints.seo,
                            { title, blocks },
                            (result) => ({ kind: 'seo', result }),
                        )
                    }
                >
                    {t('ai-assistant::Suggest SEO')}
                </ActionButton>
            </ActionGroup>

            <ActionGroup label={t('ai-assistant::Selected text')}>
                <ActionButton
                    busy={busy === 'improve'}
                    disabled={busy !== null}
                    onClick={() => rewrite('improve')}
                >
                    {t('ai-assistant::Improve')}
                </ActionButton>
                <ActionButton
                    busy={busy === 'shorten'}
                    disabled={busy !== null}
                    onClick={() => rewrite('shorten')}
                >
                    {t('ai-assistant::Shorten')}
                </ActionButton>
            </ActionGroup>

            {images.length > 0 && (
                <div className="grid gap-2">
                    <Label htmlFor="ai-image">
                        {t('ai-assistant::Alt text for an image')}
                    </Label>
                    <div className="flex gap-2">
                        <Select value={imageId} onValueChange={setImageId}>
                            <SelectTrigger
                                id="ai-image"
                                className="w-full min-w-0"
                            >
                                <SelectValue
                                    placeholder={t(
                                        'ai-assistant::Choose an image',
                                    )}
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NO_IMAGE}>
                                    {t('ai-assistant::Choose an image')}
                                </SelectItem>
                                {images.map((image) => (
                                    <SelectItem
                                        key={image.mediaId}
                                        value={String(image.mediaId)}
                                    >
                                        {image.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <ActionButton
                            busy={busy === 'alt'}
                            disabled={busy !== null || !selectedImage}
                            onClick={() =>
                                selectedImage &&
                                run<AltTextResult>(
                                    'alt',
                                    config.endpoints.altText,
                                    { media_id: selectedImage.mediaId },
                                    (result) => ({
                                        kind: 'alt',
                                        result,
                                        mediaId: selectedImage.mediaId,
                                        label: selectedImage.label,
                                    }),
                                )
                            }
                        >
                            {t('ai-assistant::Describe')}
                        </ActionButton>
                    </div>
                </div>
            )}

            {config.locales.length > 0 && (
                <div className="grid gap-2">
                    <Label htmlFor="ai-locale">
                        {t('ai-assistant::Translate the entry')}
                    </Label>
                    <div className="flex gap-2">
                        <Select value={locale} onValueChange={setLocale}>
                            <SelectTrigger
                                id="ai-locale"
                                className="w-full min-w-0"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {config.locales.map((option) => (
                                    <SelectItem
                                        key={option.code}
                                        value={option.code}
                                    >
                                        {languageName(option.code)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <ActionButton
                            busy={busy === 'translate'}
                            disabled={busy !== null || !hasText}
                            onClick={() =>
                                run<TranslateResult>(
                                    'translate',
                                    config.endpoints.translate,
                                    { title, blocks, locale },
                                    (result) => ({
                                        kind: 'translate',
                                        result,
                                        locale,
                                    }),
                                )
                            }
                        >
                            {t('ai-assistant::Translate')}
                        </ActionButton>
                    </div>
                </div>
            )}

            {error && (
                <p
                    role="alert"
                    className="text-sm text-red-700 dark:text-red-400"
                >
                    {error}
                </p>
            )}

            {preview && (
                <PreviewBox
                    onAccept={accept}
                    onDiscard={() => setPreview(null)}
                >
                    <PreviewBody preview={preview} />
                </PreviewBox>
            )}
        </SectionCard>
    );
}

// A small labelled row of buttons.
function ActionGroup({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <div className="flex flex-wrap gap-2">{children}</div>
        </div>
    );
}

function ActionButton({
    busy,
    disabled,
    onClick,
    children,
}: {
    busy: boolean;
    disabled: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <Button
            type="button"
            variant="outline"
            size="sm"
            disabled={disabled}
            onClick={onClick}
            aria-busy={busy}
        >
            {busy && <Spinner />}
            {children}
        </Button>
    );
}

// The suggestion with Accept and Discard buttons.
function PreviewBox({
    onAccept,
    onDiscard,
    children,
}: {
    onAccept: () => void;
    onDiscard: () => void;
    children: ReactNode;
}) {
    return (
        <div
            className="grid gap-3 rounded-md border bg-muted/40 p-3"
            aria-live="polite"
            data-test="ai-preview"
        >
            <div className="max-h-72 overflow-y-auto overscroll-contain text-sm">
                {children}
            </div>
            <div className="flex justify-end gap-2">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={onDiscard}
                >
                    <X />
                    {t('Discard')}
                </Button>
                <Button type="button" size="sm" onClick={onAccept}>
                    <Check />
                    {t('Accept')}
                </Button>
            </div>
        </div>
    );
}

function PreviewLabel({ children }: { children: ReactNode }) {
    return <p className="text-xs text-muted-foreground">{children}</p>;
}

// Shows each kind of suggestion as plain text. No HTML from the model is rendered.
function PreviewBody({ preview }: { preview: Preview }) {
    switch (preview.kind) {
        case 'outline':
            return (
                <div className="grid gap-3">
                    <PreviewLabel>
                        {t('ai-assistant::Outline to add to the content')}
                    </PreviewLabel>
                    {preview.result.blocks.map((block) =>
                        block.type === 'heading' ? (
                            <p
                                key={block.id}
                                className="font-medium text-foreground"
                            >
                                {htmlToText(block.content)}
                            </p>
                        ) : (
                            <ul
                                key={block.id}
                                className="-mt-2 list-disc ps-5 text-muted-foreground"
                            >
                                {listItems(block.content).map((item, i) => (
                                    <li key={i}>{item}</li>
                                ))}
                            </ul>
                        ),
                    )}
                </div>
            );
        case 'rewrite':
            return (
                <div className="grid gap-2">
                    <PreviewLabel>{t('ai-assistant::Before')}</PreviewLabel>
                    <p className="text-muted-foreground line-through decoration-zinc-300">
                        {preview.original}
                    </p>
                    <PreviewLabel>{t('ai-assistant::After')}</PreviewLabel>
                    <p className="whitespace-pre-line text-foreground">
                        {preview.result.text}
                    </p>
                </div>
            );
        case 'excerpt':
            return (
                <div className="grid gap-2">
                    <PreviewLabel>{t('Excerpt')}</PreviewLabel>
                    <p className="text-foreground">{preview.result.excerpt}</p>
                </div>
            );
        case 'seo':
            return (
                <div className="grid gap-2">
                    <PreviewLabel>{t('SEO title')}</PreviewLabel>
                    <p className="font-medium text-blue-700 dark:text-blue-400">
                        {preview.result.title}
                    </p>
                    <PreviewLabel>{t('Meta description')}</PreviewLabel>
                    <p className="text-foreground">
                        {preview.result.description}
                    </p>
                </div>
            );
        case 'alt':
            return (
                <div className="grid gap-2">
                    <PreviewLabel>
                        {t('ai-assistant::Alt text for {image}', {
                            image: preview.label,
                        })}
                    </PreviewLabel>
                    <p className="text-foreground">{preview.result.alt}</p>
                </div>
            );
        case 'translate': {
            const lines = blocksToText(preview.result.blocks);

            return (
                <div className="grid gap-2">
                    <PreviewLabel>
                        {t('ai-assistant::{language} title and content', {
                            language: languageName(preview.locale),
                        })}
                    </PreviewLabel>
                    <p className="font-medium text-foreground">
                        {preview.result.title}
                    </p>
                    {lines
                        .slice(0, TRANSLATION_PREVIEW_LINES)
                        .map((line, i) => (
                            <p key={i} className="text-muted-foreground">
                                {line}
                            </p>
                        ))}
                    {lines.length > TRANSLATION_PREVIEW_LINES && (
                        <PreviewLabel>
                            {t(
                                'ai-assistant::{count, plural, one {And # more block.} other {And # more blocks.}}',
                                {
                                    count:
                                        lines.length -
                                        TRANSLATION_PREVIEW_LINES,
                                },
                            )}
                        </PreviewLabel>
                    )}
                </div>
            );
        }
    }
}
