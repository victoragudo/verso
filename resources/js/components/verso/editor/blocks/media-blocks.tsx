import { Film, ImagePlus, Plus, X } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { MediaPicker } from '@/components/verso/media/media-picker';
import { msg, t } from '@/lib/i18n';
import type { GalleryImage, MediaItem } from '@/types';
import type { BlockFieldProps } from './shared';
import { boolAttr, stringAttr, withAttrs } from './shared';

const GALLERY_COLUMNS = ['1', '2', '3', '4', '5', '6'];

const VIDEO_FLAGS = [
    { key: 'controls', label: msg('Show controls'), fallback: true },
    { key: 'autoplay', label: msg('Autoplay (muted)'), fallback: false },
    { key: 'loop', label: msg('Loop'), fallback: false },
    { key: 'muted', label: msg('Muted'), fallback: false },
];

// Image attrs taken from a media library item.
export function imageFromMedia(media: MediaItem): GalleryImage {
    return {
        mediaId: media.id,
        src: media.url,
        alt: media.alt ?? '',
        caption: media.caption ?? '',
        width: media.width,
        height: media.height,
    };
}

// Reads the images attr of a gallery as a clean list.
export function galleryImages(value: unknown): GalleryImage[] {
    if (!Array.isArray(value)) {
        return [];
    }

    return value
        .filter(
            (item): item is Record<string, unknown> =>
                typeof item === 'object' && item !== null,
        )
        .filter((item) => typeof item.src === 'string' && item.src !== '')
        .map((item) => ({
            mediaId: typeof item.mediaId === 'number' ? item.mediaId : null,
            src: String(item.src),
            alt: typeof item.alt === 'string' ? item.alt : '',
            caption: typeof item.caption === 'string' ? item.caption : '',
            width: typeof item.width === 'number' ? item.width : null,
            height: typeof item.height === 'number' ? item.height : null,
        }));
}

// An empty state with a button to pick a file and a field to paste a link.
function MediaEmptyState({
    linkLabel,
    icon: Icon,
    onPick,
    onUrl,
}: {
    linkLabel: string;
    icon: typeof ImagePlus;
    onPick: () => void;
    onUrl: (url: string) => void;
}) {
    const [url, setUrl] = useState('');

    return (
        <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed border-zinc-300 px-4 py-6 text-center dark:border-zinc-700">
            <Icon className="size-6 text-muted-foreground" />
            <Button type="button" variant="outline" size="sm" onClick={onPick}>
                {t('Choose from the media library')}
            </Button>
            <div className="flex w-full max-w-md gap-2">
                <Input
                    aria-label={linkLabel}
                    placeholder={t('Or paste a link')}
                    value={url}
                    onChange={(event) => setUrl(event.target.value)}
                />
                <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    disabled={url.trim() === ''}
                    onClick={() => onUrl(url.trim())}
                    className="h-9"
                >
                    {t('Use')}
                </Button>
            </div>
        </div>
    );
}

// One picture with alt text and caption.
export function ImageBlockFields({ block, onChange }: BlockFieldProps) {
    const [picking, setPicking] = useState(false);
    const src = stringAttr(block, 'src');

    const select = (media: MediaItem) => {
        const image = imageFromMedia(media);
        onChange(withAttrs(block, image));
        setPicking(false);
    };

    return (
        <div className="grid gap-2">
            {src === '' ? (
                <MediaEmptyState
                    linkLabel={t('Image link')}
                    icon={ImagePlus}
                    onPick={() => setPicking(true)}
                    onUrl={(url) =>
                        onChange(withAttrs(block, { src: url, mediaId: null }))
                    }
                />
            ) : (
                <figure className="grid gap-2">
                    <img
                        src={src}
                        alt={stringAttr(block, 'alt')}
                        className="max-h-96 w-full rounded-lg border border-zinc-200 bg-zinc-50 object-contain dark:border-zinc-800"
                    />
                    <div className="auto-fit-grid gap-2 [--grid-min:13rem]">
                        <Input
                            aria-label={t('Alt text')}
                            placeholder={t(
                                'Describe the image for screen readers',
                            )}
                            value={stringAttr(block, 'alt')}
                            onChange={(event) =>
                                onChange(
                                    withAttrs(block, {
                                        alt: event.target.value,
                                    }),
                                )
                            }
                        />
                        <Input
                            aria-label={t('Caption')}
                            placeholder={t('Caption')}
                            value={stringAttr(block, 'caption')}
                            onChange={(event) =>
                                onChange(
                                    withAttrs(block, {
                                        caption: event.target.value,
                                    }),
                                )
                            }
                        />
                    </div>
                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setPicking(true)}
                        >
                            {t('Replace')}
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                                onChange(
                                    withAttrs(block, {
                                        src: '',
                                        mediaId: null,
                                        width: null,
                                        height: null,
                                    }),
                                )
                            }
                        >
                            {t('Remove image')}
                        </Button>
                    </div>
                </figure>
            )}
            <MediaPicker
                open={picking}
                onOpenChange={setPicking}
                onSelect={select}
                accept="image"
            />
        </div>
    );
}

// A grid of pictures. mediaIds and images are kept in sync.
export function GalleryBlockFields({ block, onChange }: BlockFieldProps) {
    const [picking, setPicking] = useState(false);
    const images = galleryImages(block.attrs.images);

    const setImages = (next: GalleryImage[]) =>
        onChange(
            withAttrs(block, {
                images: next,
                mediaIds: next
                    .map((image) => image.mediaId)
                    .filter((id): id is number => id !== null),
            }),
        );

    return (
        <div className="grid gap-2">
            {images.length === 0 ? (
                <MediaEmptyState
                    linkLabel={t('Gallery image link')}
                    icon={ImagePlus}
                    onPick={() => setPicking(true)}
                    onUrl={(url) =>
                        setImages([{ mediaId: null, src: url, alt: '' }])
                    }
                />
            ) : (
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                    {images.map((image, index) => (
                        <div
                            key={`${image.src}-${index}`}
                            className="group relative"
                        >
                            <img
                                src={image.src}
                                alt={image.alt}
                                className="aspect-square w-full rounded-md border border-zinc-200 object-cover dark:border-zinc-800"
                            />
                            <Button
                                type="button"
                                variant="secondary"
                                size="icon"
                                aria-label={t('Remove image {number}', {
                                    number: index + 1,
                                })}
                                onClick={() =>
                                    setImages(
                                        images.filter((_, i) => i !== index),
                                    )
                                }
                                className="absolute end-1 top-1 size-8"
                            >
                                <X />
                            </Button>
                            <Input
                                aria-label={t('Alt text for image {number}', {
                                    number: index + 1,
                                })}
                                placeholder={t('Alt text')}
                                value={image.alt}
                                onChange={(event) =>
                                    setImages(
                                        images.map((item, i) =>
                                            i === index
                                                ? {
                                                      ...item,
                                                      alt: event.target.value,
                                                  }
                                                : item,
                                        ),
                                    )
                                }
                                className="mt-1 h-8 text-xs"
                            />
                        </div>
                    ))}
                    <button
                        type="button"
                        onClick={() => setPicking(true)}
                        className="flex aspect-square min-h-10 items-center justify-center rounded-md border border-dashed border-zinc-300 text-muted-foreground hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900"
                    >
                        <Plus className="size-5" />
                        <span className="sr-only">{t('Add an image')}</span>
                    </button>
                </div>
            )}
            <div className="grid gap-2 sm:grid-cols-[10rem_1fr]">
                <Select
                    value={stringAttr(block, 'columns') || '3'}
                    onValueChange={(columns) =>
                        onChange(withAttrs(block, { columns: Number(columns) }))
                    }
                >
                    <SelectTrigger aria-label={t('Columns')}>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {GALLERY_COLUMNS.map((count) => (
                            <SelectItem key={count} value={count}>
                                {t(
                                    '{count, plural, one {# column} other {# columns}}',
                                    { count: Number(count) },
                                )}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Input
                    aria-label={t('Gallery caption')}
                    placeholder={t('Caption')}
                    value={stringAttr(block, 'caption')}
                    onChange={(event) =>
                        onChange(
                            withAttrs(block, { caption: event.target.value }),
                        )
                    }
                />
            </div>
            <div className="flex items-center gap-2">
                <Checkbox
                    id={`${block.id}-lightbox`}
                    checked={boolAttr(block, 'lightbox')}
                    onCheckedChange={(checked) =>
                        onChange(
                            withAttrs(block, { lightbox: checked === true }),
                        )
                    }
                />
                <Label htmlFor={`${block.id}-lightbox`}>
                    {t('Open the pictures in a lightbox')}
                </Label>
            </div>
            <MediaPicker
                open={picking}
                onOpenChange={setPicking}
                onSelect={(media) => {
                    setImages([...images, imageFromMedia(media)]);
                    setPicking(false);
                }}
                accept="image"
            />
        </div>
    );
}

// A video file with player options.
export function VideoBlockFields({ block, onChange }: BlockFieldProps) {
    const [picking, setPicking] = useState(false);
    const src = stringAttr(block, 'src');

    return (
        <div className="grid gap-2">
            {src === '' ? (
                <MediaEmptyState
                    linkLabel={t('Video link')}
                    icon={Film}
                    onPick={() => setPicking(true)}
                    onUrl={(url) =>
                        onChange(withAttrs(block, { src: url, mediaId: null }))
                    }
                />
            ) : (
                <>
                    <video
                        src={src}
                        controls
                        preload="metadata"
                        className="max-h-96 w-full rounded-lg border border-zinc-200 bg-black dark:border-zinc-800"
                    />
                    <div className="flex flex-wrap items-center gap-4">
                        {VIDEO_FLAGS.map((flag) => (
                            <div
                                key={flag.key}
                                className="flex items-center gap-2"
                            >
                                <Checkbox
                                    id={`${block.id}-${flag.key}`}
                                    checked={boolAttr(
                                        block,
                                        flag.key,
                                        flag.fallback,
                                    )}
                                    onCheckedChange={(checked) =>
                                        onChange(
                                            withAttrs(block, {
                                                [flag.key]: checked === true,
                                            }),
                                        )
                                    }
                                />
                                <Label htmlFor={`${block.id}-${flag.key}`}>
                                    {t(flag.label)}
                                </Label>
                            </div>
                        ))}
                    </div>
                    <div className="flex gap-2">
                        <Input
                            aria-label={t('Caption')}
                            placeholder={t('Caption')}
                            value={stringAttr(block, 'caption')}
                            onChange={(event) =>
                                onChange(
                                    withAttrs(block, {
                                        caption: event.target.value,
                                    }),
                                )
                            }
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="h-9"
                            onClick={() =>
                                onChange(
                                    withAttrs(block, {
                                        src: '',
                                        mediaId: null,
                                    }),
                                )
                            }
                        >
                            {t('Remove video')}
                        </Button>
                    </div>
                </>
            )}
            <MediaPicker
                open={picking}
                onOpenChange={setPicking}
                onSelect={(media) => {
                    onChange(
                        withAttrs(block, { src: media.url, mediaId: media.id }),
                    );
                    setPicking(false);
                }}
                accept="video"
            />
        </div>
    );
}
