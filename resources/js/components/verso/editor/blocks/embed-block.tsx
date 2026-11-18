import { Input } from '@/components/ui/input';
import { t } from '@/lib/i18n';
import type { BlockFieldProps } from './shared';
import { stringAttr, withAttrs } from './shared';

const YOUTUBE_PATTERN =
    /^https?:\/\/(?:www\.|m\.)?(?:youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|live\/)|youtu\.be\/|youtube-nocookie\.com\/embed\/)([A-Za-z0-9_-]{11})/;
const VIMEO_PATTERN =
    /^https?:\/\/(?:www\.|player\.)?vimeo\.com\/(?:video\/|channels\/[\w-]+\/|groups\/[\w-]+\/videos\/)?(\d+)/;

// The privacy friendly player for a YouTube or Vimeo link, like the server renders it.
export function embedPreviewUrl(url: string): string | null {
    const youtube = url.trim().match(YOUTUBE_PATTERN);

    if (youtube) {
        return `https://www.youtube-nocookie.com/embed/${youtube[1]}`;
    }

    const vimeo = url.trim().match(VIMEO_PATTERN);

    return vimeo ? `https://player.vimeo.com/video/${vimeo[1]}?dnt=1` : null;
}

// A video link with a live preview.
export function EmbedBlockFields({ block, onChange }: BlockFieldProps) {
    const url = stringAttr(block, 'url');
    const preview = embedPreviewUrl(url);

    return (
        <div className="grid gap-2">
            <Input
                aria-label={t('Video link')}
                type="url"
                placeholder={t(
                    'https://www.youtube.com/watch?v=... or https://vimeo.com/...',
                )}
                value={url}
                autoComplete="off"
                spellCheck={false}
                onChange={(event) =>
                    onChange(withAttrs(block, { url: event.target.value }))
                }
            />
            {preview ? (
                <div className="aspect-video overflow-hidden rounded-lg border border-zinc-200 bg-zinc-100 dark:border-zinc-800 dark:bg-zinc-900">
                    <iframe
                        src={preview}
                        title={stringAttr(block, 'title') || t('Video preview')}
                        loading="lazy"
                        referrerPolicy="strict-origin-when-cross-origin"
                        sandbox="allow-scripts allow-same-origin allow-presentation"
                        className="size-full"
                    />
                </div>
            ) : (
                url !== '' && (
                    <p className="text-xs text-muted-foreground">
                        {t(
                            'Only YouTube and Vimeo show a player. Other links are shown as a plain link.',
                        )}
                    </p>
                )
            )}
            <div className="auto-fit-grid gap-2 [--grid-min:13rem]">
                <Input
                    aria-label={t('Video title')}
                    placeholder={t('Title for screen readers')}
                    value={stringAttr(block, 'title')}
                    onChange={(event) =>
                        onChange(
                            withAttrs(block, { title: event.target.value }),
                        )
                    }
                />
                <Input
                    aria-label={t('Caption')}
                    placeholder={t('Caption')}
                    value={stringAttr(block, 'caption')}
                    onChange={(event) =>
                        onChange(
                            withAttrs(block, { caption: event.target.value }),
                        )
                    }
                />
            </div>
        </div>
    );
}
