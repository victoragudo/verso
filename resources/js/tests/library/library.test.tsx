import {
    act,
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { builder } from '@/builder';
import { applyManifest } from '@/builder/core';
import { library, libraryDialog, syncedBlockFor } from '@/builder/library';
import { resetBuilder, setupBuilder } from '@/builder/setup';
import type { EditorManifest, LibraryItem } from '@/builder/types';
import { SyncedBlockFields } from '@/components/verso/editor/blocks/synced-block';
import { SaveToLibraryDialog } from '@/components/verso/editor/save-to-library';
import type { Block } from '@/types';

const banner: LibraryItem = {
    id: 12,
    kind: 'synced',
    title: 'Opening hours banner',
    category: 'Banners',
    keywords: ['hours'],
    blocks: [
        {
            id: 'b_h',
            type: 'paragraph',
            attrs: {},
            content: 'Open every day from 7am.',
            children: [],
        },
    ],
    updatedAt: null,
};

const manifest: EditorManifest = {
    version: 1,
    categories: [],
    styleProperties: [],
    breakpoints: [{ key: 'base', maxWidth: null }],
    features: ['add_panel'],
    allowedBlocks: null,
    limits: { colors: null, fonts: null, spacing: null },
    library: {
        items: [banner],
        canSave: true,
        storeUrl: '/admin/library',
        editUrl: '/admin/library/0',
    },
};

beforeEach(() => {
    resetBuilder();
    setupBuilder({});
    applyManifest(builder, manifest);
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('library insert source', () => {
    it('adds synced items as a synced block and patterns as copies', () => {
        library.set(
            [
                banner,
                { ...banner, id: 13, kind: 'pattern', title: 'Hero pattern' },
            ],
            {
                canSave: true,
                storeUrl: '/admin/library',
                editUrl: '/admin/library/0',
            },
        );
        const source = builder.insertSources.get('core/library');
        const items = source?.items({ blocks: [], patterns: [] }) ?? [];

        expect(items.map((item) => item.label)).toEqual([
            'Opening hours banner',
            'Hero pattern',
        ]);
        expect(items[0].create()[0]).toMatchObject({
            type: 'synced',
            attrs: { ref: 12 },
        });
        expect(items[1].create()[0]).toMatchObject({
            type: 'paragraph',
            content: 'Open every day from 7am.',
        });
        expect(items[1].create()[0].id).not.toBe('b_h');
        expect(library.editUrl(12)).toBe('/admin/library/12');
    });
});

describe('SyncedBlockFields', () => {
    it('turns a synced block into normal blocks', () => {
        const onChange = vi.fn();
        render(
            <SyncedBlockFields
                block={syncedBlockFor(banner, 'b_s')}
                onChange={onChange}
            />,
        );

        expect(screen.getByText('Opening hours banner')).toBeInTheDocument();
        fireEvent.click(
            screen.getByRole('button', { name: 'Turn into normal blocks' }),
        );

        const section = onChange.mock.calls[0][0] as Block;
        expect(section.type).toBe('section');
        expect(section.children?.[0].content).toBe('Open every day from 7am.');
    });

    it('says when the item was deleted', () => {
        render(
            <SyncedBlockFields
                block={{
                    id: 'b_s',
                    type: 'synced',
                    attrs: { ref: 404 },
                    content: '',
                    children: [],
                }}
                onChange={vi.fn()}
            />,
        );

        expect(
            screen.getByText(/deleted from the library/),
        ).toBeInTheDocument();
    });
});

describe('SaveToLibraryDialog', () => {
    it('saves the blocks as a synced block', async () => {
        const saved = { ...banner, id: 20, title: 'Footer promise' };
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ item: saved }),
        });
        vi.stubGlobal('fetch', fetchMock);
        const onSaved = vi.fn();
        render(
            <SaveToLibraryDialog
                blocks={banner.blocks}
                onClose={vi.fn()}
                onSaved={onSaved}
            />,
        );

        fireEvent.change(screen.getByLabelText('Name'), {
            target: { value: 'Footer promise' },
        });
        await act(async () => {
            fireEvent.click(screen.getByRole('button', { name: 'Save' }));
        });

        await waitFor(() => expect(onSaved).toHaveBeenCalledWith(saved));
        expect(
            JSON.parse(fetchMock.mock.calls[0][1].body as string),
        ).toMatchObject({ kind: 'synced', title: 'Footer promise' });
        expect(library.find(20)?.title).toBe('Footer promise');
    });

    it('opens from anywhere through the dialog store', () => {
        const onSaved = vi.fn();
        libraryDialog.open(banner.blocks, onSaved);

        expect(libraryDialog.read()?.blocks).toHaveLength(1);
        libraryDialog.close();
        expect(libraryDialog.read()).toBeNull();
    });
});
