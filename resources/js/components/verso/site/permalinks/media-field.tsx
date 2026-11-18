import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { VersoIcon } from '@/components/verso/icons';
import { MediaPicker } from '@/components/verso/media/media-picker';
import { t } from '@/lib/i18n';
import type { PickedMedia } from '@/types';

// Picks one image from the media library, shows a small preview and can clear it.
export function MediaField({
    id,
    value,
    onChange,
    emptyText,
}: {
    id: string;
    value: PickedMedia | null;
    onChange: (media: PickedMedia | null) => void;
    emptyText: string;
}) {
    const [open, setOpen] = useState(false);

    return (
        <div className="flex min-w-0 flex-wrap items-center gap-3">
            {value ? (
                <img
                    src={value.url}
                    alt=""
                    width={64}
                    height={40}
                    loading="lazy"
                    decoding="async"
                    className="h-10 w-16 rounded border bg-muted object-cover"
                />
            ) : (
                <span className="flex h-10 w-16 items-center justify-center rounded border border-dashed text-muted-foreground">
                    <VersoIcon name="media" size={16} />
                </span>
            )}
            <span className="min-w-0 flex-1 truncate text-sm text-muted-foreground">
                {value?.filename ?? emptyText}
            </span>
            <Button
                id={id}
                type="button"
                variant="outline"
                size="sm"
                className="min-h-10 md:min-h-8"
                onClick={() => setOpen(true)}
            >
                {value ? t('Change') : t('Choose image')}
            </Button>
            {value && (
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="min-h-10 md:min-h-8"
                    onClick={() => onChange(null)}
                >
                    {t('Remove')}
                </Button>
            )}
            <MediaPicker
                open={open}
                onOpenChange={setOpen}
                accept="image"
                onSelect={(media) => {
                    onChange({
                        id: media.id,
                        url: media.url,
                        filename: media.filename,
                    });
                    setOpen(false);
                }}
            />
        </div>
    );
}
