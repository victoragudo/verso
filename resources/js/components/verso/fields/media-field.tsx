import { ChevronLeft, ImageIcon, Paperclip, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { MediaPicker } from '@/components/verso/media/media-picker';
import { MediaThumb } from '@/components/verso/media/media-thumb';
import { t } from '@/lib/i18n';
import type { MediaItem, MediaLibraryItem } from '@/types';
import { mediaById, moveItem } from './index';

type Props = {
    kind: 'image' | 'file' | 'gallery';
    value: number | number[] | null;
    onChange: (value: number | number[] | null) => void;
    label: string;
    maxItems?: number | null;
};

// A media item made from what the picker returns, until the full item is loaded.
function asLibraryItem(media: MediaItem): MediaLibraryItem {
    return {
        ...media,
        path: media.filename,
        type: media.mime_type.startsWith('image/') ? 'image' : 'document',
        thumbnail_url: null,
        uploaded_by: null,
        created_at: null,
        can: { update: false, delete: false },
    };
}

// Image, file or gallery field: picks media from the library and shows small previews.
export function MediaField({ kind, value, onChange, label, maxItems }: Props) {
    const ids = Array.isArray(value) ? value : value ? [value] : [];
    const [items, setItems] = useState<Map<number, MediaLibraryItem>>(
        new Map(),
    );
    const [picking, setPicking] = useState(false);
    const idsKey = ids.join(',');

    useEffect(() => {
        const missing = idsKey
            .split(',')
            .filter((item) => item !== '')
            .map(Number)
            .filter((item) => !items.has(item));

        if (missing.length === 0) {
            return;
        }

        mediaById(missing)
            .then((loaded) =>
                setItems((current) => {
                    const next = new Map(current);
                    loaded.forEach((item) => next.set(item.id, item));

                    return next;
                }),
            )
            .catch(() => undefined);
        // Only new ids need loading.
    }, [idsKey]);

    const select = (media: MediaItem) => {
        setItems((current) =>
            new Map(current).set(media.id, asLibraryItem(media)),
        );

        if (kind === 'gallery') {
            onChange(ids.includes(media.id) ? ids : [...ids, media.id]);
        } else {
            onChange(media.id);
        }
    };

    const remove = (id: number) =>
        onChange(
            kind === 'gallery' ? ids.filter((other) => other !== id) : null,
        );
    const full =
        kind !== 'gallery'
            ? ids.length > 0
            : maxItems != null && ids.length >= maxItems;

    return (
        <div className="grid gap-2">
            {ids.length > 0 && (
                <ul
                    className={
                        kind === 'gallery'
                            ? 'grid grid-cols-3 gap-2 sm:grid-cols-4'
                            : 'grid gap-2'
                    }
                    aria-label={t('{label} items', { label })}
                >
                    {ids.map((id, index) => {
                        const item = items.get(id);

                        return (
                            <li
                                key={id}
                                className="group relative overflow-hidden rounded-md border bg-muted/40"
                            >
                                {kind === 'file' ? (
                                    <div className="flex min-h-10 items-center gap-2 px-3 text-sm">
                                        <Paperclip className="size-4 text-muted-foreground" />
                                        <span className="truncate">
                                            {item?.filename ??
                                                t('File #{id}', { id })}
                                        </span>
                                    </div>
                                ) : (
                                    <div
                                        className={
                                            kind === 'image'
                                                ? 'aspect-video'
                                                : 'aspect-square'
                                        }
                                    >
                                        {item ? (
                                            <MediaThumb media={item} />
                                        ) : (
                                            <div className="flex size-full items-center justify-center text-xs text-muted-foreground">
                                                #{id}
                                            </div>
                                        )}
                                    </div>
                                )}
                                <div className="absolute end-1 top-1 flex gap-1">
                                    {kind === 'gallery' && index > 0 && (
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="secondary"
                                            className="size-7"
                                            aria-label={t(
                                                'Move {name} earlier',
                                                {
                                                    name: item?.filename ?? id,
                                                },
                                            )}
                                            onClick={() =>
                                                onChange(
                                                    moveItem(
                                                        ids,
                                                        index,
                                                        index - 1,
                                                    ),
                                                )
                                            }
                                        >
                                            <ChevronLeft />
                                        </Button>
                                    )}
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="secondary"
                                        className="size-7"
                                        aria-label={t('Remove {name}', {
                                            name: item?.filename ?? id,
                                        })}
                                        onClick={() => remove(id)}
                                    >
                                        <X />
                                    </Button>
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}
            {!full && (
                <Button
                    type="button"
                    variant="outline"
                    className="justify-self-start"
                    onClick={() => setPicking(true)}
                >
                    {kind === 'file' ? <Paperclip /> : <ImageIcon />}
                    {kind === 'gallery'
                        ? t('Add images')
                        : kind === 'image'
                          ? t('Choose image')
                          : t('Choose file')}
                </Button>
            )}
            <MediaPicker
                open={picking}
                onOpenChange={setPicking}
                onSelect={(media) => {
                    select(media);
                    setPicking(false);
                }}
                accept={kind === 'file' ? 'any' : 'image'}
            />
        </div>
    );
}
