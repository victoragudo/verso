import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { fetchLibrary, uploadMedia } from '@/components/verso/media/media-api';
import { MediaPicker } from '@/components/verso/media/media-picker';
import type { MediaItem } from '@/types';
import { libraryPage, mediaItem } from './fixtures';

vi.mock('@/components/verso/media/media-api', () => ({
    fetchLibrary: vi.fn(),
    uploadMedia: vi.fn(),
    acceptAttribute: (accept: string) =>
        accept === 'any' ? undefined : `${accept}/*`,
}));

vi.mock('sonner', () => ({
    toast: { success: vi.fn(), error: vi.fn() },
}));

const storefront = mediaItem(1, {
    filename: 'storefront.jpg',
    alt: 'The shop window at dawn',
});
const ovens = mediaItem(2, { filename: 'ovens.jpg', alt: 'Stone ovens' });
const menu = mediaItem(3, {
    filename: 'winter-menu.pdf',
    type: 'document',
    mime_type: 'application/pdf',
    thumbnail_url: null,
});

function renderPicker(accept?: 'image' | 'video' | 'any') {
    const onSelect = vi.fn<(media: MediaItem) => void>();
    const onOpenChange = vi.fn<(open: boolean) => void>();

    render(
        <MediaPicker
            open
            onOpenChange={onOpenChange}
            onSelect={onSelect}
            accept={accept}
        />,
    );

    return { onSelect, onOpenChange };
}

const selectButton = () => screen.getByRole('button', { name: 'Select' });

beforeEach(() => {
    vi.mocked(fetchLibrary).mockResolvedValue(
        libraryPage([storefront, ovens, menu]),
    );
});

describe('MediaPicker', () => {
    it('loads the first page of the library when it opens', async () => {
        renderPicker();

        expect(
            await screen.findByRole('button', {
                name: 'The shop window at dawn',
            }),
        ).toBeInTheDocument();
        expect(fetchLibrary).toHaveBeenCalledWith({
            search: '',
            accept: 'any',
            page: 1,
        });
    });

    it('does not load anything while closed', () => {
        render(
            <MediaPicker
                open={false}
                onOpenChange={vi.fn()}
                onSelect={vi.fn()}
            />,
        );

        expect(fetchLibrary).not.toHaveBeenCalled();
    });

    it('asks only for images when it accepts images', async () => {
        renderPicker('image');

        await screen.findByRole('button', { name: 'Stone ovens' });

        expect(fetchLibrary).toHaveBeenCalledWith(
            expect.objectContaining({ accept: 'image' }),
        );
        expect(
            screen.getByText('Choose an image or upload a new one.'),
        ).toBeInTheDocument();
        expect(screen.getByTestId('media-picker-upload')).toHaveAttribute(
            'accept',
            'image/*',
        );
    });

    it('names files without alt text by their file name', async () => {
        renderPicker();

        expect(
            await screen.findByRole('button', { name: 'winter-menu.pdf' }),
        ).toBeInTheDocument();
    });

    it('starts with nothing selected', async () => {
        renderPicker();

        await screen.findByRole('button', { name: 'Stone ovens' });

        expect(screen.getByText('Nothing selected')).toBeInTheDocument();
        expect(selectButton()).toBeDisabled();
    });

    it('selects a file with one click', async () => {
        renderPicker();

        const tile = await screen.findByRole('button', { name: 'Stone ovens' });
        await userEvent.click(tile);

        expect(tile).toHaveAttribute('aria-pressed', 'true');
        expect(screen.getByText('ovens.jpg')).toBeInTheDocument();
        expect(selectButton()).toBeEnabled();
    });

    it('keeps only one file selected', async () => {
        renderPicker();

        const first = await screen.findByRole('button', {
            name: 'The shop window at dawn',
        });
        const second = screen.getByRole('button', { name: 'Stone ovens' });

        await userEvent.click(first);
        await userEvent.click(second);

        expect(first).toHaveAttribute('aria-pressed', 'false');
        expect(second).toHaveAttribute('aria-pressed', 'true');
    });

    it('returns the selected file and closes with Select', async () => {
        const { onSelect, onOpenChange } = renderPicker();

        await userEvent.click(
            await screen.findByRole('button', { name: 'Stone ovens' }),
        );
        await userEvent.click(selectButton());

        expect(onSelect).toHaveBeenCalledWith(ovens);
        expect(onOpenChange).toHaveBeenCalledWith(false);
    });

    it('returns a file right away on double click', async () => {
        const { onSelect, onOpenChange } = renderPicker();

        await userEvent.dblClick(
            await screen.findByRole('button', { name: 'winter-menu.pdf' }),
        );

        expect(onSelect).toHaveBeenCalledWith(menu);
        expect(onOpenChange).toHaveBeenCalledWith(false);
    });

    it('closes without a file on Cancel', async () => {
        const { onSelect, onOpenChange } = renderPicker();

        await userEvent.click(
            await screen.findByRole('button', { name: 'Cancel' }),
        );

        expect(onSelect).not.toHaveBeenCalled();
        expect(onOpenChange).toHaveBeenCalledWith(false);
    });

    it('searches with the trimmed query', async () => {
        renderPicker();
        await screen.findByRole('button', { name: 'Stone ovens' });

        vi.mocked(fetchLibrary).mockResolvedValue(libraryPage([ovens]));

        await userEvent.type(
            screen.getByRole('searchbox', { name: 'Search media' }),
            '  ovens  {Enter}',
        );

        await waitFor(() =>
            expect(fetchLibrary).toHaveBeenLastCalledWith({
                search: 'ovens',
                accept: 'any',
                page: 1,
            }),
        );
        await waitFor(() =>
            expect(
                screen.queryByRole('button', {
                    name: 'The shop window at dawn',
                }),
            ).not.toBeInTheDocument(),
        );
    });

    it('explains when a search finds nothing', async () => {
        renderPicker();
        await screen.findByRole('button', { name: 'Stone ovens' });

        vi.mocked(fetchLibrary).mockResolvedValue(libraryPage([]));

        await userEvent.type(
            screen.getByRole('searchbox', { name: 'Search media' }),
            'croissant{Enter}',
        );

        expect(
            await screen.findByText('No files match your search.'),
        ).toBeInTheDocument();
    });

    it('explains when the library is empty', async () => {
        vi.mocked(fetchLibrary).mockResolvedValue(libraryPage([]));

        renderPicker();

        expect(
            await screen.findByText('No files yet. Upload one to get started.'),
        ).toBeInTheDocument();
    });

    it('shows the error the server sent', async () => {
        vi.mocked(fetchLibrary).mockRejectedValue(
            new Error('This action is unauthorized.'),
        );

        renderPicker();

        expect(
            await screen.findByText('This action is unauthorized.'),
        ).toBeInTheDocument();
    });

    it('shows a general error for unknown failures', async () => {
        vi.mocked(fetchLibrary).mockRejectedValue('offline');

        renderPicker();

        expect(
            await screen.findByText('Could not load the media library.'),
        ).toBeInTheDocument();
    });

    it('loads more files and keeps the first page', async () => {
        vi.mocked(fetchLibrary).mockResolvedValueOnce(
            libraryPage([storefront, ovens], 1, 2),
        );
        vi.mocked(fetchLibrary).mockResolvedValueOnce(
            libraryPage([menu], 2, 2),
        );

        renderPicker();

        await userEvent.click(
            await screen.findByRole('button', { name: 'Load more' }),
        );

        expect(
            await screen.findByRole('button', { name: 'winter-menu.pdf' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Stone ovens' }),
        ).toBeInTheDocument();
        expect(fetchLibrary).toHaveBeenLastCalledWith({
            search: '',
            accept: 'any',
            page: 2,
        });
        expect(
            screen.queryByRole('button', { name: 'Load more' }),
        ).not.toBeInTheDocument();
    });

    it('puts an uploaded file first and selects it', async () => {
        const bakery = mediaItem(9, {
            filename: 'new-bakery.jpg',
            alt: 'The new bakery in Porto',
        });
        vi.mocked(uploadMedia).mockResolvedValue(bakery);

        renderPicker('image');
        await screen.findByRole('button', { name: 'Stone ovens' });

        await userEvent.upload(
            screen.getByTestId('media-picker-upload'),
            new File(['jpeg'], 'new-bakery.jpg', { type: 'image/jpeg' }),
        );

        const tile = await screen.findByRole('button', {
            name: 'The new bakery in Porto',
        });

        expect(tile).toHaveAttribute('aria-pressed', 'true');
        expect(within(screen.getByRole('list')).getAllByRole('button')[0]).toBe(
            tile,
        );
    });

    it('does not select an uploaded file of the wrong kind', async () => {
        vi.mocked(uploadMedia).mockResolvedValue(
            mediaItem(10, {
                filename: 'price-list.pdf',
                type: 'document',
                mime_type: 'application/pdf',
            }),
        );

        renderPicker('image');
        await screen.findByRole('button', { name: 'Stone ovens' });

        await userEvent.upload(
            screen.getByTestId('media-picker-upload'),
            new File(['pdf'], 'price-list.pdf', { type: 'application/pdf' }),
            { applyAccept: false },
        );

        await waitFor(() => expect(uploadMedia).toHaveBeenCalled());

        expect(
            screen.queryByRole('button', { name: 'price-list.pdf' }),
        ).not.toBeInTheDocument();
        expect(screen.getByText('Nothing selected')).toBeInTheDocument();
    });
});

describe('MediaPicker slow responses', () => {
    it('ignores an old search that answers after a newer one', async () => {
        let answerFirst: (page: ReturnType<typeof libraryPage>) => void = () =>
            undefined;

        vi.mocked(fetchLibrary).mockReturnValueOnce(
            new Promise((resolve) => {
                answerFirst = resolve;
            }),
        );
        vi.mocked(fetchLibrary).mockResolvedValueOnce(libraryPage([ovens]));

        renderPicker();

        await userEvent.type(
            screen.getByRole('searchbox', { name: 'Search media' }),
            'ovens{Enter}',
        );

        expect(
            await screen.findByRole('button', { name: 'Stone ovens' }),
        ).toBeInTheDocument();

        answerFirst(libraryPage([storefront, menu]));

        await waitFor(() =>
            expect(
                screen.queryByRole('button', { name: 'winter-menu.pdf' }),
            ).not.toBeInTheDocument(),
        );
        await new Promise((resolve) => setTimeout(resolve, 20));

        expect(
            screen.queryByRole('button', { name: 'The shop window at dawn' }),
        ).not.toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Stone ovens' }),
        ).toBeInTheDocument();
    });
});
