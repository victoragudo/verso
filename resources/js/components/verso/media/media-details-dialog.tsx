import { Link } from '@inertiajs/react';
import { Copy, ExternalLink, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import {
    deleteMedia,
    fetchMedia,
    MediaRequestError,
    saveMedia,
} from '@/components/verso/media/media-api';
import { MediaThumb } from '@/components/verso/media/media-thumb';
import { useClipboard } from '@/hooks/use-clipboard';
import { useSiteDates } from '@/hooks/use-site-dates';
import { formatBytes, formatNumber, t } from '@/lib/i18n';
import type { MediaDetail, MediaLibraryItem } from '@/types';

type Props = {
    media: MediaLibraryItem | null;
    onOpenChange: (open: boolean) => void;
    onChanged: () => void;
};

// Shows one file: preview, details, alt text form, where it is used and delete.
export function MediaDetailsDialog({ media, onOpenChange, onChanged }: Props) {
    return (
        <Dialog open={media !== null} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                {media && (
                    <MediaDetails
                        key={media.id}
                        media={media}
                        onClose={() => onOpenChange(false)}
                        onChanged={onChanged}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function MediaDetails({
    media,
    onClose,
    onChanged,
}: {
    media: MediaLibraryItem;
    onClose: () => void;
    onChanged: () => void;
}) {
    const [detail, setDetail] = useState<MediaDetail | null>(null);
    const [alt, setAlt] = useState(media.alt ?? '');
    const [caption, setCaption] = useState(media.caption ?? '');
    const [saving, setSaving] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [, copy] = useClipboard();
    const { formatDay } = useSiteDates();

    useEffect(() => {
        let active = true;

        fetchMedia(media.id)
            .then((data) => active && setDetail(data))
            .catch(() => active && setDetail(null));

        return () => {
            active = false;
        };
    }, [media.id]);

    const usage = detail?.usage ?? [];

    const save = async (event: FormEvent) => {
        event.preventDefault();
        setSaving(true);

        try {
            await saveMedia(media.id, { alt, caption });
            toast.success(t('File details saved.'));
            onChanged();
        } catch (error) {
            toast.error(
                error instanceof Error ? error.message : t('Could not save.'),
            );
        } finally {
            setSaving(false);
        }
    };

    const remove = async () => {
        const question =
            usage.length > 0
                ? t(
                      '{count, plural, one {This file is used in # entry.} other {This file is used in # entries.}} Those entries will show a broken file. Delete it anyway?',
                      { count: usage.length },
                  )
                : t('Delete "{name}"? This cannot be undone.', {
                      name: media.filename,
                  });

        if (!window.confirm(question)) {
            return;
        }

        setDeleting(true);

        try {
            await deleteMedia(media.id, usage.length > 0);
            toast.success(t('File deleted.'));
            onClose();
            onChanged();
        } catch (error) {
            toast.error(
                error instanceof MediaRequestError
                    ? error.message
                    : t('Could not delete the file.'),
            );
        } finally {
            setDeleting(false);
        }
    };

    const details: [string, string][] = [
        [t('Type'), media.mime_type],
        [t('Size'), formatBytes(media.size)],
        ...(media.width && media.height
            ? [
                  [
                      t('Dimensions'),
                      t('{width} x {height} px', {
                          width: formatNumber(media.width),
                          height: formatNumber(media.height),
                      }),
                  ] as [string, string],
              ]
            : []),
        [t('Uploaded by'), media.uploaded_by ?? t('Unknown')],
        [t('Uploaded on'), formatDay(media.created_at)],
    ];

    return (
        <>
            <DialogHeader>
                <DialogTitle className="truncate pe-6">
                    {media.filename}
                </DialogTitle>
                <DialogDescription>{t('File details')}</DialogDescription>
            </DialogHeader>
            <div className="grid gap-6 md:grid-cols-[1fr_18rem]">
                <div className="flex flex-col gap-3">
                    <div className="flex aspect-[4/3] items-center justify-center overflow-hidden rounded-lg border bg-muted">
                        {media.type === 'image' ? (
                            <img
                                src={media.url}
                                alt={media.alt ?? ''}
                                className="max-h-full max-w-full object-contain"
                            />
                        ) : media.type === 'video' ? (
                            <video
                                src={media.url}
                                controls
                                className="max-h-full max-w-full"
                            />
                        ) : media.type === 'audio' ? (
                            <audio src={media.url} controls />
                        ) : (
                            <MediaThumb media={media} />
                        )}
                    </div>
                    <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                        {details.map(([label, value]) => (
                            <div key={label} className="contents">
                                <dt className="text-muted-foreground">
                                    {label}
                                </dt>
                                <dd className="truncate tabular-nums">
                                    {value}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </div>
                <div className="flex flex-col gap-5">
                    <form onSubmit={save} className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="media-alt">{t('Alt text')}</Label>
                            <Input
                                id="media-alt"
                                value={alt}
                                onChange={(event) => setAlt(event.target.value)}
                                maxLength={255}
                                disabled={!media.can.update}
                                placeholder={t(
                                    'Describe the image for people who cannot see it',
                                )}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="media-caption">
                                {t('Caption')}
                            </Label>
                            <Textarea
                                id="media-caption"
                                value={caption}
                                onChange={(event) =>
                                    setCaption(event.target.value)
                                }
                                rows={3}
                                disabled={!media.can.update}
                            />
                        </div>
                        {media.can.update && (
                            <Button type="submit" disabled={saving}>
                                {saving && <Spinner />}
                                {t('Save details')}
                            </Button>
                        )}
                    </form>

                    <div className="grid gap-2">
                        <Label htmlFor="media-url">{t('File URL')}</Label>
                        <div className="flex gap-2">
                            <Input
                                id="media-url"
                                value={media.url}
                                readOnly
                                className="text-xs"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                aria-label={t('Copy URL')}
                                onClick={() =>
                                    copy(media.url).then(
                                        (copied) =>
                                            copied &&
                                            toast.success(t('URL copied.')),
                                    )
                                }
                            >
                                <Copy />
                            </Button>
                            <Button
                                variant="outline"
                                size="icon"
                                asChild
                                aria-label={t('Open file')}
                            >
                                <a
                                    href={media.url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <ExternalLink />
                                </a>
                            </Button>
                        </div>
                    </div>

                    <div className="grid gap-2 text-sm">
                        <span className="font-medium">{t('Used in')}</span>
                        {detail === null ? (
                            <span className="text-muted-foreground">
                                {t('Checking...')}
                            </span>
                        ) : usage.length === 0 ? (
                            <span className="text-muted-foreground">
                                {t('Not used in any entry.')}
                            </span>
                        ) : (
                            <ul className="grid gap-1">
                                {usage.map((entry) => (
                                    <li key={entry.id} className="truncate">
                                        <Link
                                            href={entry.edit_url}
                                            className="text-blue-600 hover:underline dark:text-blue-400"
                                        >
                                            {entry.title}
                                        </Link>
                                        {entry.trashed && (
                                            <span className="text-muted-foreground">
                                                {' '}
                                                {t('(in trash)')}
                                            </span>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    {media.can.delete && (
                        <Button
                            type="button"
                            variant="outline"
                            className="text-destructive-foreground"
                            onClick={remove}
                            disabled={deleting || detail === null}
                        >
                            <Trash2 />
                            {t('Delete file')}
                        </Button>
                    )}
                </div>
            </div>
        </>
    );
}
