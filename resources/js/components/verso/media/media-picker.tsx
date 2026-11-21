import { Check, ImageIcon, Search, Upload } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import {
    acceptAttribute,
    fetchLibrary,
} from '@/components/verso/media/media-api';
import { MediaThumb } from '@/components/verso/media/media-thumb';
import { useMediaUpload } from '@/components/verso/media/use-media-upload';
import { msg, t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { MediaItem, MediaLibraryItem } from '@/types';

// Dialog to pick a file from the media library, or upload a new one.
export type MediaPickerProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onSelect: (media: MediaItem) => void;
    accept?: 'image' | 'video' | 'any';
    // Where focus goes on close. Call event.preventDefault() to move it yourself.
    onCloseAutoFocus?: (event: Event) => void;
};

const DESCRIPTIONS = {
    image: msg('Choose an image or upload a new one.'),
    video: msg('Choose a video or upload a new one.'),
    any: msg('Choose a file or upload a new one.'),
};

export function MediaPicker({
    open,
    onOpenChange,
    onSelect,
    accept = 'any',
    onCloseAutoFocus,
}: MediaPickerProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="max-h-[90vh] flex-col open:flex sm:max-w-4xl"
                onCloseAutoFocus={onCloseAutoFocus}
            >
                <DialogHeader>
                    <DialogTitle>{t('Media library')}</DialogTitle>
                    <DialogDescription>
                        {t(DESCRIPTIONS[accept])}
                    </DialogDescription>
                </DialogHeader>
                {open && (
                    <PickerBody
                        accept={accept}
                        onSelect={(media) => {
                            onSelect(media);
                            onOpenChange(false);
                        }}
                        onCancel={() => onOpenChange(false)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function PickerBody({
    accept,
    onSelect,
    onCancel,
}: {
    accept: 'image' | 'video' | 'any';
    onSelect: (media: MediaItem) => void;
    onCancel: () => void;
}) {
    const [items, setItems] = useState<MediaLibraryItem[]>([]);
    const [search, setSearch] = useState('');
    const [query, setQuery] = useState('');
    const [page, setPage] = useState(1);
    const [hasMore, setHasMore] = useState(false);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [selected, setSelected] = useState<MediaLibraryItem | null>(null);
    const input = useRef<HTMLInputElement>(null);
    // Numbers each library request, so a slow old answer can not replace a newer one.
    const latestRequest = useRef(0);
    const uploader = useMediaUpload();

    const load = useCallback(
        async (nextPage: number, append: boolean) => {
            const request = ++latestRequest.current;
            const isStale = () => request !== latestRequest.current;

            setLoading(true);
            setError(null);

            try {
                const result = await fetchLibrary({
                    search: query,
                    accept,
                    page: nextPage,
                });

                if (isStale()) {
                    return;
                }

                setItems((current) =>
                    append ? [...current, ...result.data] : result.data,
                );
                setPage(result.current_page);
                setHasMore(result.current_page < result.last_page);
            } catch (exception) {
                if (isStale()) {
                    return;
                }

                setError(
                    exception instanceof Error
                        ? exception.message
                        : t('Could not load the media library.'),
                );
            } finally {
                if (!isStale()) {
                    setLoading(false);
                }
            }
        },
        [query, accept],
    );

    useEffect(() => {
        void load(1, false);
    }, [load]);

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        setQuery(search.trim());
    };

    const uploadFiles = async (files: FileList | null) => {
        if (!files || files.length === 0) {
            return;
        }

        const uploaded = await uploader.upload(files);
        const matching = uploaded.filter(
            (item) => accept === 'any' || item.type === accept,
        );

        if (matching.length > 0) {
            setItems((current) => [...matching.reverse(), ...current]);
            setSelected(matching[0]);
        }
    };

    return (
        <>
            <div className="flex flex-col gap-2 sm:flex-row">
                <form onSubmit={submitSearch} className="flex flex-1 gap-2">
                    <Input
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder={t('Search media')}
                        aria-label={t('Search media')}
                    />
                    <Button type="submit" variant="outline" size="icon">
                        <Search />
                        <span className="sr-only">{t('Search')}</span>
                    </Button>
                </form>
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => input.current?.click()}
                    disabled={uploader.isUploading}
                >
                    {uploader.isUploading ? <Spinner /> : <Upload />}
                    {t('Upload')}
                </Button>
                <input
                    ref={input}
                    type="file"
                    multiple
                    accept={acceptAttribute(accept)}
                    className="hidden"
                    data-testid="media-picker-upload"
                    onChange={(event) => {
                        void uploadFiles(event.target.files);
                        event.target.value = '';
                    }}
                />
            </div>

            <div className="min-h-64 flex-1 overflow-y-auto">
                {error ? (
                    <p className="py-12 text-center text-sm text-destructive-foreground">
                        {error}
                    </p>
                ) : items.length === 0 && !loading ? (
                    <div className="flex flex-col items-center gap-2 py-12 text-center text-sm text-muted-foreground">
                        <ImageIcon className="size-8" />
                        {query
                            ? t('No files match your search.')
                            : t('No files yet. Upload one to get started.')}
                    </div>
                ) : (
                    <ul className="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-5">
                        {items.map((item) => {
                            const isSelected = selected?.id === item.id;

                            return (
                                <li key={item.id}>
                                    <button
                                        type="button"
                                        onClick={() => setSelected(item)}
                                        onDoubleClick={() => onSelect(item)}
                                        aria-pressed={isSelected}
                                        aria-label={item.alt || item.filename}
                                        title={item.filename}
                                        className={cn(
                                            'relative block aspect-square w-full overflow-hidden rounded-md border bg-muted transition-colors hover:border-ring/60 focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:outline-none',
                                            isSelected &&
                                                'ring-2 ring-blue-600 ring-offset-2 ring-offset-background',
                                        )}
                                    >
                                        <MediaThumb media={item} />
                                        {isSelected && (
                                            <span className="absolute end-1.5 top-1.5 flex size-5 items-center justify-center rounded-full bg-blue-600 text-white">
                                                <Check className="size-3.5" />
                                            </span>
                                        )}
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                )}
                {loading && (
                    <div className="flex justify-center py-6">
                        <Spinner />
                    </div>
                )}
                {hasMore && !loading && (
                    <div className="flex justify-center py-4">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => void load(page + 1, true)}
                        >
                            {t('Load more')}
                        </Button>
                    </div>
                )}
            </div>

            <DialogFooter className="items-center gap-2 sm:justify-between">
                <span className="truncate text-sm text-muted-foreground">
                    {selected ? selected.filename : t('Nothing selected')}
                </span>
                <div className="flex gap-2">
                    <Button type="button" variant="outline" onClick={onCancel}>
                        {t('Cancel')}
                    </Button>
                    <Button
                        type="button"
                        disabled={selected === null}
                        onClick={() => selected && onSelect(selected)}
                    >
                        {t('Select')}
                    </Button>
                </div>
            </DialogFooter>
        </>
    );
}

export default MediaPicker;
