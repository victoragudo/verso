import { FileText, Film, EighthNotes } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { MediaLibraryItem } from '@/types';

const ICONS = {
    video: Film,
    audio: EighthNotes,
    document: FileText,
};

// A square preview: the image thumbnail, or an icon with the file extension.
export function MediaThumb({
    media,
    className,
}: {
    media: MediaLibraryItem;
    className?: string;
}) {
    if (media.type === 'image') {
        return (
            <img
                src={media.thumbnail_url ?? media.url}
                alt={media.alt ?? ''}
                loading="lazy"
                decoding="async"
                className={cn('size-full object-cover', className)}
            />
        );
    }

    const Icon = ICONS[media.type];
    const extension = media.filename.split('.').pop()?.toUpperCase() ?? '';

    return (
        <div
            className={cn(
                'flex size-full flex-col items-center justify-center gap-2 bg-muted text-muted-foreground',
                className,
            )}
        >
            <Icon className="size-8" strokeWidth={1.5} />
            <span className="text-xs font-medium tracking-wide">
                {extension}
            </span>
        </div>
    );
}
