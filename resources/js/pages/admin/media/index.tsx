import { Head, router } from '@inertiajs/react';
import { Search, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import type { DragEvent, FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Pagination } from '@/components/verso/content/pagination';
import { MediaDetailsDialog } from '@/components/verso/media/media-details-dialog';
import { MediaThumb } from '@/components/verso/media/media-thumb';
import { useMediaUpload } from '@/components/verso/media/use-media-upload';
import { EmptyState, FilterTabs, PageHeader } from '@/components/verso/ui';
import { formatBytes, formatNumber, t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { index } from '@/routes/admin/media';
import type { MediaKind, MediaLibraryItem, Paginated } from '@/types';

type Props = {
    media: Paginated<MediaLibraryItem>;
    filters: { search: string; type: MediaKind | null };
    types: { value: MediaKind; label: string }[];
    upload: { max_kb: number; accept: string[] };
};

const ALL_TYPES = 'all';

// The media library: a grid of files with search, type filter and uploads.
export default function MediaIndex({ media, filters, types, upload }: Props) {
    const [search, setSearch] = useState(filters.search);
    const [selected, setSelected] = useState<MediaLibraryItem | null>(null);
    const [dragging, setDragging] = useState(false);
    const input = useRef<HTMLInputElement>(null);
    const uploader = useMediaUpload();

    const visit = (query: { search?: string; type?: string | null }) => {
        const next = { search: filters.search, type: filters.type, ...query };

        router.get(
            index.url({
                query: {
                    search: next.search || undefined,
                    type: next.type || undefined,
                },
            }),
            {},
            { preserveState: true, preserveScroll: true },
        );
    };

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        visit({ search });
    };

    const reload = () => router.reload({ only: ['media'] });

    const uploadFiles = async (files: FileList | null) => {
        if (!files || files.length === 0) {
            return;
        }

        const uploaded = await uploader.upload(files);

        if (uploaded.length > 0) {
            reload();
        }
    };

    const onDrop = (event: DragEvent) => {
        event.preventDefault();
        setDragging(false);
        void uploadFiles(event.dataTransfer.files);
    };

    const typeHref = (type: string | null) =>
        index.url({
            query: {
                search: filters.search || undefined,
                type: type ?? undefined,
            },
        });

    const tabs = [
        { key: ALL_TYPES, label: t('All types'), href: typeHref(null) },
        ...types.map((type) => ({
            key: type.value,
            label: type.label,
            href: typeHref(type.value),
        })),
    ];

    const openPicker = () => input.current?.click();

    return (
        <>
            <Head title={t('Media')} />
            <div
                onDragOver={(event) => {
                    event.preventDefault();
                    setDragging(true);
                }}
                onDragLeave={() => setDragging(false)}
                onDrop={onDrop}
            >
                <PageHeader
                    icon="media"
                    title={t('Media')}
                    description={t(
                        'Images, videos and files for your site. Up to {size} each.',
                        { size: formatBytes(upload.max_kb * 1024) },
                    )}
                    actions={
                        <Button
                            onClick={openPicker}
                            disabled={uploader.isUploading}
                        >
                            {uploader.isUploading ? <Spinner /> : <Upload />}
                            {uploader.isUploading
                                ? t('Uploading {count}...', {
                                      count: uploader.uploading,
                                  })
                                : t('Upload files')}
                        </Button>
                    }
                />
                <input
                    ref={input}
                    type="file"
                    multiple
                    accept={upload.accept.join(',')}
                    className="hidden"
                    onChange={(event) => {
                        void uploadFiles(event.target.files);
                        event.target.value = '';
                    }}
                />

                <div className="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <FilterTabs
                        tabs={tabs}
                        active={filters.type ?? ALL_TYPES}
                        label={t('Filter by type')}
                    />
                    <form
                        onSubmit={submitSearch}
                        role="search"
                        className="relative w-full lg:w-72"
                    >
                        <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={t(
                                'Search by name, alt text or caption',
                            )}
                            aria-label={t('Search media')}
                            className="ps-9"
                        />
                    </form>
                </div>

                <div
                    className={cn(
                        'rounded-lg border border-transparent transition-colors',
                        dragging &&
                            'border-dashed border-brand bg-brand-soft/50',
                    )}
                >
                    {media.data.length === 0 ? (
                        <div className="rounded-lg border bg-card shadow-xs">
                            <EmptyState
                                icon="media"
                                title={
                                    filters.search || filters.type
                                        ? t('No files match your search.')
                                        : t(
                                              'No files yet. Drop files here or upload them.',
                                          )
                                }
                                action={
                                    <Button
                                        variant="outline"
                                        onClick={openPicker}
                                    >
                                        <Upload />
                                        {t('Upload files')}
                                    </Button>
                                }
                            />
                        </div>
                    ) : (
                        <ul className="auto-grid gap-3 [--grid-min:9rem] sm:[--grid-min:11rem]">
                            {media.data.map((item) => (
                                <li key={item.id} className="min-w-0">
                                    <button
                                        type="button"
                                        onClick={() => setSelected(item)}
                                        className="group flex w-full flex-col overflow-hidden rounded-lg border bg-card text-start shadow-xs transition-colors outline-none hover:border-foreground/20 focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    >
                                        <span className="aspect-square w-full overflow-hidden border-b bg-muted">
                                            <MediaThumb media={item} />
                                        </span>
                                        <span className="flex min-w-0 flex-col gap-0.5 px-2.5 py-2">
                                            <span className="truncate text-sm font-medium text-foreground">
                                                {item.filename}
                                            </span>
                                            <span className="truncate text-xs text-muted-foreground tabular-nums">
                                                {formatBytes(item.size)}
                                                {item.width && item.height
                                                    ? ` · ${t('{width} x {height}', { width: formatNumber(item.width), height: formatNumber(item.height) })}`
                                                    : ''}
                                            </span>
                                        </span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                <div className="mt-4">
                    <Pagination page={media} />
                </div>
            </div>

            <MediaDetailsDialog
                media={selected}
                onOpenChange={(open) => !open && setSelected(null)}
                onChanged={reload}
            />
        </>
    );
}

MediaIndex.layout = () => ({
    breadcrumbs: [
        {
            title: t('Media'),
            href: index(),
        },
    ],
});
