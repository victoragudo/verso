import { destroy, library, show, store, update } from '@/routes/admin/media';
import { t } from '@/lib/i18n';
import { xsrfToken } from '@/lib/json-request';
import type { MediaDetail, MediaLibraryItem, MediaLibraryPage } from '@/types';

// Small fetch helpers for the media JSON endpoints.

export type LibraryQuery = {
    search?: string;
    accept?: 'image' | 'video' | 'any';
    page?: number;
};

// Error with the message Laravel sent back.
export class MediaRequestError extends Error {
    constructor(
        message: string,
        public status: number,
    ) {
        super(message);
    }
}

async function request<T>(url: string, init: RequestInit = {}): Promise<T> {
    const headers = new Headers(init.headers);
    headers.set('Accept', 'application/json');
    headers.set('X-Requested-With', 'XMLHttpRequest');
    headers.set('X-XSRF-TOKEN', xsrfToken());

    const response = await fetch(url, {
        credentials: 'same-origin',
        ...init,
        headers,
    });

    if (response.status === 204) {
        return undefined as T;
    }

    const body = (await response.json().catch(() => ({}))) as {
        message?: string;
        errors?: Record<string, string[]>;
    };

    if (!response.ok) {
        const firstError = body.errors
            ? Object.values(body.errors)[0]?.[0]
            : undefined;

        throw new MediaRequestError(
            firstError ?? body.message ?? t('Something went wrong.'),
            response.status,
        );
    }

    return body as T;
}

export function fetchLibrary(query: LibraryQuery): Promise<MediaLibraryPage> {
    const params: Record<string, string | number> = {};

    if (query.search) {
        params.search = query.search;
    }

    if (query.accept && query.accept !== 'any') {
        params.accept = query.accept;
    }

    if (query.page && query.page > 1) {
        params.page = query.page;
    }

    return request<MediaLibraryPage>(library.url({ query: params }));
}

export function fetchMedia(id: number): Promise<MediaDetail> {
    return request<MediaDetail>(show.url(id));
}

export function uploadMedia(file: File): Promise<MediaLibraryItem> {
    const body = new FormData();
    body.append('file', file);

    return request<MediaLibraryItem>(store.url(), { method: 'POST', body });
}

export function saveMedia(
    id: number,
    data: { alt: string; caption: string },
): Promise<MediaLibraryItem> {
    return request<MediaLibraryItem>(update.url(id), {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
    });
}

export function deleteMedia(id: number, force = false): Promise<void> {
    return request<void>(destroy.url(id), {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ force }),
    });
}

// The file types an upload input should offer for a picker filter.
export function acceptAttribute(
    accept: 'image' | 'video' | 'any',
): string | undefined {
    return accept === 'any' ? undefined : `${accept}/*`;
}
