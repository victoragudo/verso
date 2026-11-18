import type { MediaLibraryItem, MediaLibraryPage } from '@/types';

// A media item as the library endpoint sends it, with realistic values.
export function mediaItem(
    id: number,
    overrides: Partial<MediaLibraryItem> = {},
): MediaLibraryItem {
    const filename = overrides.filename ?? `harbor-lane-${id}.jpg`;

    return {
        id,
        url: `/storage/media/${filename}`,
        filename,
        mime_type: 'image/jpeg',
        size: 245_760,
        width: 1600,
        height: 1067,
        alt: null,
        caption: null,
        path: `media/${filename}`,
        type: 'image',
        thumbnail_url: `/img/media/${filename}?w=320`,
        uploaded_by: 'Lucía Romero',
        created_at: '2026-11-10T09:15:00Z',
        can: { update: true, delete: true },
        ...overrides,
    };
}

// One page of the media library.
export function libraryPage(
    data: MediaLibraryItem[],
    currentPage = 1,
    lastPage = 1,
): MediaLibraryPage {
    return {
        data,
        current_page: currentPage,
        last_page: lastPage,
        per_page: 30,
        total: data.length,
        next_page_url:
            currentPage < lastPage
                ? `/admin/media/library?page=${currentPage + 1}`
                : null,
    };
}
